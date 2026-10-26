<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;
use Modules\Tenancy\Exceptions\MissingCompanyContext;
use Modules\Tenancy\Models\Contract;
use Modules\Tenancy\Models\Site;
use Modules\Tenancy\Support\CompanyContext;

class CompanyScopeTest extends TenancyTestCase
{
    private function seedTwo(): array
    {
        $a = $this->company('AAA');
        $b = $this->company('BBB');
        foreach ([$a, $b] as $c) {
            CompanyContext::run($c->id, function () use ($c) {
                $site = Site::query()->create(['code' => 'KRM', 'name' => 'Keerom '.$c->code]); // same code in both: unique is per company
                Contract::query()->create(['site_id' => $site->id, 'number' => 'K-1', 'employer_name' => 'BWS',
                    'value_dpp' => 1000, 'value_total' => 1110]);
            });
        }

        return [$a, $b];
    }

    public function test_user_of_a_sees_only_a_rows_on_every_model(): void
    {
        [$a, $b] = $this->seedTwo();

        foreach ([Site::class, Contract::class] as $model) {
            $this->assertSame(2, $model::withoutGlobalScopes()->count(), "$model seed");
            CompanyContext::set($a->id);
            $this->assertSame([$a->id], $model::query()->pluck('company_id')->all(), $model.' as A');
            CompanyContext::set($b->id);
            $this->assertSame([$b->id], $model::query()->pluck('company_id')->all(), $model.' as B');
        }
    }

    public function test_no_context_returns_zero_rows_not_everything(): void
    {
        $this->seedTwo();
        CompanyContext::clear();
        $this->assertSame(0, Site::query()->count());
        $this->assertSame(0, Contract::query()->count());
    }

    public function test_creating_without_context_throws(): void
    {
        $this->expectException(MissingCompanyContext::class);
        Site::query()->create(['code' => 'X', 'name' => 'X']);
    }

    public function test_cannot_write_a_row_into_another_company(): void
    {
        [$a, $b] = $this->seedTwo();
        CompanyContext::set($a->id);
        $this->expectException(MissingCompanyContext::class);
        Site::query()->create(['company_id' => $b->id, 'code' => 'Z', 'name' => 'Z']);
    }

    public function test_find_by_id_across_companies_is_blocked(): void
    {
        [$a, $b] = $this->seedTwo();
        $bSiteId = Site::withoutGlobalScopes()->where('company_id', $b->id)->value('id');
        CompanyContext::set($a->id);
        $this->assertNull(Site::query()->find($bSiteId));
    }

    public function test_across_companies_forbidden_for_non_holding_and_allowed_for_holding(): void
    {
        [$a, $b] = $this->seedTwo();
        $plain = $this->user();
        $this->member($plain, $a);
        $this->actingAs($plain);
        try {
            Site::query()->acrossCompanies()->get();
            $this->fail('non-holding user must not lift the scope');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $boss = $this->user();
        $this->holding($boss);
        $this->actingAs($boss);
        $this->assertSame(2, Site::query()->acrossCompanies()->count());
    }

    public function test_guest_cannot_lift_scope(): void
    {
        $this->seedTwo();
        $this->expectException(AuthorizationException::class);
        Site::query()->acrossCompanies()->get();
    }

    public function test_middleware_403_without_membership_and_sets_context_with_it(): void
    {
        [$a, $b] = $this->seedTwo();
        Route::middleware(['web', 'company.active'])->get('/_probe', fn () => ['company_id' => CompanyContext::id(), 'sites' => Site::query()->pluck('code')]);

        $u = $this->user();
        $this->member($u, $a);

        $this->actingAs($u)->withSession(['active_company_id' => $b->id])->get('/_probe')->assertForbidden();
        $this->actingAs($u)->get('/_probe')->assertForbidden(); // no active company chosen
        $this->actingAs($u)->withSession(['active_company_id' => $a->id])->get('/_probe')
            ->assertOk()->assertJson(['company_id' => $a->id, 'sites' => ['KRM']]);
        $this->assertNull(CompanyContext::id(), 'context restored after request');
    }

    public function test_holding_role_alone_does_not_grant_company_context(): void
    {
        [$a] = $this->seedTwo();
        Route::middleware(['web', 'company.active'])->get('/_probe2', fn () => 'ok');
        $boss = $this->user();
        $this->holding($boss);
        $this->actingAs($boss)->withSession(['active_company_id' => $a->id])->get('/_probe2')->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Tenancy\Models\Company;
use Modules\Tenancy\Support\CompanyContext;
use Tests\TestCase;

abstract class TenancyTestCase extends TestCase
{
    use RefreshDatabase;

    protected function company(string $code): Company
    {
        return Company::query()->create(['code' => $code, 'name' => "PT {$code}"]);
    }

    protected function user(?string $email = null): User
    {
        $email ??= uniqid('u').'@example.test';

        return User::query()->forceCreate(['name' => $email, 'email' => $email, 'password' => bcrypt('x')]);
    }

    protected function member(User $u, Company $c, string $role = 'project_admin'): void
    {
        DB::table('tn_company_user')->insert(['user_id' => $u->id, 'company_id' => $c->id, 'role' => $role]);
    }

    protected function holding(User $u, string $role = 'holding_viewer'): void
    {
        DB::table('tn_management_viewers')->insert(['user_id' => $u->id, 'role' => $role, 'granted_at' => now()]);
    }

    protected function tearDown(): void
    {
        CompanyContext::clear();
        parent::tearDown();
    }
}

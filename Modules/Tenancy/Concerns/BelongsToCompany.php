<?php

namespace Modules\Tenancy\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Modules\Tenancy\Exceptions\MissingCompanyContext;
use Modules\Tenancy\Models\Company;
use Modules\Tenancy\Scopes\CompanyScope;
use Modules\Tenancy\Support\CompanyContext;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            $model->company_id ??= CompanyContext::id() ?? throw new MissingCompanyContext;

            // Never let a row be written into another company than the active one.
            $active = CompanyContext::id();
            if ($active !== null && (int) $model->company_id !== $active) {
                throw new MissingCompanyContext('company_id tidak sama dengan perusahaan aktif.');
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /** The single way to lift the scope; holding (read-only) only. */
    public function scopeAcrossCompanies(Builder $query): Builder
    {
        Gate::authorize('holding-read');

        return $query->withoutGlobalScope(CompanyScope::class);
    }
}

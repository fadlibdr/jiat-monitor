<?php

namespace Modules\Tenancy\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Modules\Tenancy\Support\CompanyContext;

/** No context => `1 = 0` (zero rows), so a forgotten context can never leak. */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $id = CompanyContext::id();

        if ($id === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('company_id'), $id);
    }
}

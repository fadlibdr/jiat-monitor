<?php

namespace Modules\Tenancy\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Tenancy\Http\Middleware\SetActiveCompany;
use Modules\Tenancy\Models\ManagementViewer;
use Modules\Tenancy\Support\CompanyContext;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // scoped(): one instance per request / queued job, never a process-wide static
        // (LLD §3). Tests get a fresh one per test because the container is rebuilt.
        $this->app->scoped(CompanyContext::class, fn () => new CompanyContext);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Route::aliasMiddleware('company.active', SetActiveCompany::class);

        // Holding = read-only across companies; the only door to acrossCompanies().
        Gate::define('holding-read', fn ($user) => $user !== null
            && ManagementViewer::query()->where('user_id', $user->getKey())->exists());
    }
}

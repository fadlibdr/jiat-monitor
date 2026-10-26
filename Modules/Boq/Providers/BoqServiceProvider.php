<?php

namespace Modules\Boq\Providers;

use Illuminate\Support\ServiceProvider;

class BoqServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}

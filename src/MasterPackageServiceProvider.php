<?php

namespace Bangsamu\Master;

use Illuminate\Support\ServiceProvider;

class MasterPackageServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->publishes([
            __DIR__.'/../resources/config/SsoConfig.php' => config_path('SsoConfig.php'),
        ]);
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'master');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/master'),
        ]);

        // $this->publishes([
        //     __DIR__.'/../resources/views/' => resource_path('views/adminlte/auth/login.blade.php'),
        // ]);

        $this->publishes([
            __DIR__.'/routes.php' => base_path('routes/master.php'),
        ]);
    }
}

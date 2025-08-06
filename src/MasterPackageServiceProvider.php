<?php

namespace Bangsamu\Master;

use Illuminate\Support\ServiceProvider;
use Jenssegers\Agent\Agent as Agent;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;

class MasterPackageServiceProvider extends ServiceProvider
{
    /**
     * The prefix to use for register/load the package resources.
     *
     * @var string
     */
    protected $pkgPrefix = 'master';

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
        $agent = new Agent();
        View::share('agent', $agent);
        //
        $this->loadConfig();
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->publishes([
            __DIR__ . '/../resources/config/MasterConfig.php' => config_path('MasterConfig.php'),
        ]);
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'master');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/master'),
        ]);

        // $this->publishes([
        //     __DIR__.'/../resources/views/' => resource_path('views/adminlte/auth/login.blade.php'),
        // ]);

        $this->publishes([
            __DIR__ . '/routes.php' => base_path('routes/master.php'),
        ]);


        // componen
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'master');

        Blade::componentNamespace('BangsaMu\\Master\\Components', 'master');
    }

    /**
     * Load the package config.
     *
     * @return void
     */
    private function loadConfig()
    {
        $configPath = $this->packagePath('resources/config/' . ucfirst($this->pkgPrefix) . 'Config' . '.php');
        $configPath2 = $this->packagePath('resources/config/' . ucfirst($this->pkgPrefix) . 'CrudConfig' . '.php');
        $this->mergeConfigFrom($configPath, ucfirst($this->pkgPrefix . 'Config'));
        $this->mergeConfigFrom($configPath2, ucfirst($this->pkgPrefix . 'CrudConfig'));
        // dd(config());
    }

    /**
     * Get the absolute path to some package resource.
     *
     * @param  string  $path  The relative path to the resource
     * @return string
     */
    private function packagePath($path)
    {
        return __DIR__ . "/../$path";
    }
}

<?php

namespace Bangsamu\Master;

use Illuminate\Support\ServiceProvider;
use Jenssegers\Agent\Agent as Agent;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;
use Symfony\Component\Finder\Finder;
use Illuminate\Support\Str;

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

        // componen & view master
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



        // // Path ke folder komponen
        // $componentPath = __DIR__.'/Components';

        // // Namespace dasar komponen
        // $baseNamespace = 'Bangsamu\\Master\\Components\\';

        // // Daftar semua file PHP di folder Components
        // foreach (Finder::create()->files()->in($componentPath)->name('*.php') as $file) {
        //     $filename = $file->getFilenameWithoutExtension(); // contoh: "Menu"
        //     $class = $baseNamespace . $filename;

        //     if (class_exists($class)) {
        //         // Ubah ke nama kebab-case untuk Blade component
        //         $alias = Str::kebab($filename); // "menu", "input-label"

        //         // Daftarkan Blade component <x-master::menu />
        //         dd($class, $alias);
        //         Blade::component($class, $alias, 'master');
        //     }
        // }
        Blade::componentNamespace('Bangsamu\\Master\\Components', 'master');
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
        $configMenu = $this->packagePath('resources/config/' . ucfirst($this->pkgPrefix) . 'Menu' . '.php');
        $this->mergeConfigFrom($configPath, ucfirst($this->pkgPrefix . 'Config'));
        $this->mergeConfigFrom($configPath2, ucfirst($this->pkgPrefix . 'CrudConfig'));
        $this->mergeConfigFrom($configMenu, ucfirst($this->pkgPrefix . 'Menu'));
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

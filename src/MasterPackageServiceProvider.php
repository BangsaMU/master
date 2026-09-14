<?php

namespace Bangsamu\Master;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;
use Symfony\Component\Finder\Finder;

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
        $this->app->singleton(\Bangsamu\Master\Services\DynamicAssetService::class, function ($app) {
            return new \Bangsamu\Master\Services\DynamicAssetService;
        });

        $this->app->singleton(\Bangsamu\Master\Services\MasterBroadcastService::class, function ($app) {
            return new \Bangsamu\Master\Services\MasterBroadcastService;
        });

        $this->app->singleton(\Bangsamu\Master\Services\MasterItemSyncService::class, function ($app) {
            return new \Bangsamu\Master\Services\MasterItemSyncService;
        });

        $this->app->singleton(\Bangsamu\Master\Services\MasterDataSyncService::class, function ($app) {
            return new \Bangsamu\Master\Services\MasterDataSyncService;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (file_exists(__DIR__.'/helpers.php')) {
            require_once __DIR__.'/helpers.php';
        }

        $agent = new Agent;
        View::share('agent', $agent);
        //
        $this->loadConfig();
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->publishes([
            __DIR__.'/../resources/config/MasterConfig.php' => config_path('MasterConfig.php'),
        ]);

        // componen & view master
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
        Blade::componentNamespace('Bangsamu\\Master\\Components', 'master');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Bangsamu\Master\Commands\MasterCatchUpCommand::class,
                \Bangsamu\Master\Commands\MasterSyncLockCommand::class,
            ]);

            // Auto-register scheduled master sync catch-up if enabled
            if (config('MasterConfig.sync.schedule_enabled', true)) {
                $this->app->booted(function () {
                    try {
                        $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
                        $frequency = (string) config('MasterConfig.sync.schedule_frequency', 'hourly');
                        $limit = (int) config('MasterConfig.sync.schedule_limit', 50);

                        $event = $schedule->command("master:catch-up --limit={$limit}")
                            ->name('bangsamu-master-catch-up')
                            ->withoutOverlapping(60)
                            ->runInBackground();

                        match ($frequency) {
                            'everyMinute' => $event->everyMinute(),
                            'everyFiveMinutes' => $event->everyFiveMinutes(),
                            'everyTenMinutes' => $event->everyTenMinutes(),
                            'everyFifteenMinutes' => $event->everyFifteenMinutes(),
                            'everyThirtyMinutes' => $event->everyThirtyMinutes(),
                            'everyTwoHours' => $event->cron('0 */2 * * *'),
                            'everyThreeHours' => $event->cron('0 */3 * * *'),
                            'everySixHours' => $event->cron('0 */6 * * *'),
                            'daily' => $event->daily(),
                            'hourly' => $event->hourly(),
                            default => (str_contains($frequency, ' ') ? $event->cron($frequency) : $event->hourly()),
                        };
                    } catch (\Throwable $e) {
                        // Suppress if scheduler is unavailable
                    }
                });
            }
        }
    }

    /**
     * Load the package config.
     *
     * @return void
     */
    private function loadConfig()
    {
        $configPath = $this->packagePath('resources/config/'.ucfirst($this->pkgPrefix).'Config'.'.php');
        $configPath2 = $this->packagePath('resources/config/'.ucfirst($this->pkgPrefix).'CrudConfig'.'.php');
        $configMenu = $this->packagePath('resources/config/'.ucfirst($this->pkgPrefix).'Menu'.'.php');
        $this->mergeConfigFrom($configPath, ucfirst($this->pkgPrefix.'Config'));
        $this->mergeConfigFrom($configPath2, ucfirst($this->pkgPrefix.'CrudConfig'));
        $this->mergeConfigFrom($configMenu, ucfirst($this->pkgPrefix.'Menu'));
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
        return __DIR__."/../$path";
    }
}

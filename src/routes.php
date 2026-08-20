<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;

Route::get('mud-master', function () {
    // dd(config());
    $value = config('MasterConfig.main.APP_CODE');
    echo 'Hello from the master package!' . json_encode($value);
});

Route::prefix('master')->get('mud-view', function () {
    return view('master::mud');
});

Route::middleware(['web','auth'])->prefix('redis')->name('redis.')->group(function () {
    Route::get('/inspector', [\Bangsamu\Master\Controllers\RedisInspectorController::class, 'index'])->name('inspector');
});

Route::middleware(['web','auth'])->prefix('support')->name('support.')->group(function () {
    Route::get('ticket-email', [\Bangsamu\Master\Controllers\SupportTicketController::class, 'ticketEmail'])
        ->name('ticket-email');
    Route::get('ticket-email/{id?}', [\Bangsamu\Master\Controllers\SupportTicketController::class, 'ticketEmailView'])
        ->name('ticket-email.view');
    Route::post('ticket-store', [\Bangsamu\Master\Controllers\SupportTicketController::class, 'ticketStore'])
        ->name('ticket-store');
});


Route::middleware(['web','auth'])->prefix('master')->name('master.')->group(function () {
    // Route::get('user', [\Bangsamu\Master\Controllers\UserController::class, 'index'])->name('user.index');

    Route::resource('category', \Bangsamu\Master\Controllers\CategoryController::class);
    Route::post('category/import', [\Bangsamu\Master\Controllers\CategoryController::class, 'import'])->name('category.import');
    // Route::get('category/importtemplate', [\Bangsamu\Master\Controllers\CategoryController::class, 'importtemplate'])->name('category.importtemplate');

    Route::resource('company', \Bangsamu\Master\Controllers\CompanyController::class);
    Route::post('company/import', [\Bangsamu\Master\Controllers\CompanyController::class, 'import'])->name('company.import');
    Route::post('company/{id}/update-template-json', [\Bangsamu\Master\Controllers\CompanyController::class, 'updateTemplateJson'])->name('company.update_template_json');

    // Route::resource('company', \Bangsamu\Master\Controllers\CompanyController::class);
    Route::resource('department', \Bangsamu\Master\Controllers\DepartmentController::class);
    Route::post('department/import', [\Bangsamu\Master\Controllers\DepartmentController::class, 'import'])->name('department.import');

    Route::get('item-code/export', [\Bangsamu\Master\Controllers\ItemCodeController::class, 'export'])->name('item-code.export');
    Route::get('item-code/importtemplate', [\Bangsamu\Master\Controllers\ItemCodeController::class, 'importtemplate'])->name('item-code.importtemplate');
    Route::resource('item-code', \Bangsamu\Master\Controllers\ItemCodeController::class);
    Route::post('item-code/import', [\Bangsamu\Master\Controllers\ItemCodeController::class, 'import'])->name('item-code.import');

    Route::resource('item-group', \Bangsamu\Master\Controllers\ItemGroupController::class);
    Route::post('item-group/import', [\Bangsamu\Master\Controllers\ItemGroupController::class, 'import'])->name('item-group.import');

    Route::resource('location', \Bangsamu\Master\Controllers\LocationController::class);
    Route::post('location/import', [\Bangsamu\Master\Controllers\LocationController::class, 'import'])->name('location.import');

    Route::resource('vendor', \Bangsamu\Master\Controllers\VendorController::class);
    Route::post('vendor/import', [\Bangsamu\Master\Controllers\VendorController::class, 'import'])->name('vendor.import');

    Route::resource('pca', \Bangsamu\Master\Controllers\PcaController::class);
    Route::post('pca/import', [\Bangsamu\Master\Controllers\PcaController::class, 'import'])->name('pca.import');

    // belum test Route::get('project/importtemplate', [\Bangsamu\Master\Controllers\ProjectController::class, 'importtemplate'])->name('project.importtemplate');
    Route::resource('project', \Bangsamu\Master\Controllers\ProjectController::class);
    Route::post('project/import', [\Bangsamu\Master\Controllers\ProjectController::class, 'import'])->name('project.import');

    // Route::resource('project2', \Bangsamu\Master\Controllers\ProjectController2::class);
    // Route::post('project2/import', [\Bangsamu\Master\Controllers\ProjectController2::class, 'import'])->name('project2.import');

    Route::resource('project-detail', \Bangsamu\Master\Controllers\ProjectDetailController::class);
    Route::post('project-detail/import', [\Bangsamu\Master\Controllers\ProjectDetailController::class, 'import'])->name('project-detail.import');


    Route::resource('uom', \Bangsamu\Master\Controllers\UomController::class);
    Route::post('uom/import', [\Bangsamu\Master\Controllers\UomController::class, 'import'])->name('uom.import');

    Route::resource('priority', \Bangsamu\Master\Controllers\PriorityController::class);


    // Route::resource('mcu', \Bangsamu\Master\Controllers\McuController::class);
    // Route::post('mcu/store-json', [\Bangsamu\Master\Controllers\McuController::class, 'storeJson'])->name('mcu.store.json');
    // Route::post('mcu/import', [\Bangsamu\Master\Controllers\McuController::class, 'import'])->name('mcu.import');

    Route::resource('employee', \Bangsamu\Master\Controllers\EmployeeController::class);
    Route::post('employee/import', [\Bangsamu\Master\Controllers\EmployeeController::class, 'import'])->name('employee.import');

    Route::get('get{table}/export', [\Bangsamu\Master\Controllers\ExportController::class, 'export'])->name('table.export');


    //belum test Route::resource('report', \Bangsamu\Master\Controllers\ReportController::class);

});



Route::middleware(['web','auth'])->prefix('crud')->name('crud.')->group(function () {
    // AURO CRUD Tabel
Route::get('{table}', [\Bangsamu\Master\Controllers\CrudTabelController::class, 'index'])
    ->name('index'); // List all

Route::get('{table}/create', [\Bangsamu\Master\Controllers\CrudTabelController::class, 'create'])
    ->name('create'); // Form create

Route::post('{table}', [\Bangsamu\Master\Controllers\CrudTabelController::class, 'store'])
    ->name('store'); // Handle form submission

Route::get('{table}/{id}', [\Bangsamu\Master\Controllers\CrudTabelController::class, 'show'])
    ->name('show'); // View a single row

Route::get('{table}/{id}/edit', [\Bangsamu\Master\Controllers\CrudTabelController::class, 'edit'])
    ->name('edit'); // Form edit

Route::put('{table}/{id}', [\Bangsamu\Master\Controllers\CrudTabelController::class, 'update'])
    ->name('update'); // Handle update submission

Route::delete('{table}/{id}', [\Bangsamu\Master\Controllers\CrudTabelController::class, 'destroy'])
    ->name('destroy'); // Handle delete

});


Route::prefix('api')
    ->middleware(['api'])->group(function () {
        // Route::get('get-ip',  [Bangsamu\Master\Controllers\MasterCrulController::class, 'getIp'])
        //     ->name('get-ip');

        /*untuk webhook update dari master*/
        Route::post('/sync-master/{tabel?}/{id?}', [\Bangsamu\Master\Controllers\MasterController::class, 'syncTabel'])
            ->name('sync-master');
        /*untuk manual get sync ke DB master*/
        Route::get('/master-{tabel}/{id?}', [\Bangsamu\Master\Controllers\MasterController::class, 'tabel'])
            ->name('master-tabel');
        /*untuk get lokal DB*/
        Route::get('/get-{tabel}/{id?}', [\Bangsamu\Master\Controllers\MasterController::class, 'getTabel'])
            ->name('get-tabel');
        /*untuk get Master DB*/
        Route::get('/getMaster-{tabel}/{id?}', [\Bangsamu\Master\Controllers\MasterController::class, 'getMaster'])
            ->name('get-Mastertabel');

        /*list data master*/
        Route::get('getitemcodebyparams', [\Bangsamu\Master\Controllers\ApiController::class, 'getItemCodeByParams'])
            ->name('getitemcodebyparams');
        Route::get('getuombyparams', [\Bangsamu\Master\Controllers\ApiController::class, 'getUomByParams'])
            ->name('getuombyparams');
        Route::get('getpcabyparams', [\Bangsamu\Master\Controllers\ApiController::class, 'getPcaByParams'])
            ->name('getpcabyparams');
        Route::get('getcategorybyparams', [\Bangsamu\Master\Controllers\ApiController::class, 'getCategoryByParams'])
            ->name('getcategorybyparams');
        Route::get('getitemgroupbyparams', [\Bangsamu\Master\Controllers\ApiController::class, 'getItemGroupByParams'])
            ->name('getitemgroupbyparams');
        Route::get('getprojectbyparams', [\Bangsamu\Master\Controllers\ApiController::class, 'getProjectByParams'])
            ->name('getprojectbyparams');
        Route::get('getdepartmentbyparams', [\Bangsamu\Master\Controllers\ApiController::class, 'getDepartmentByParams'])
            ->name('getdepartmentbyparams');
        Route::get('getlocationbyparams', [\Bangsamu\Master\Controllers\ApiController::class, 'getLocationByParams'])
            ->name('getlocationbyparams');
        Route::get('getemployeebyparams', [\Bangsamu\Master\Controllers\ApiController::class, 'getEmployeeByParams'])
            ->name('getemployeebyparams');
        Route::get('getbrandbyparams', [\Bangsamu\Master\Controllers\ApiController::class, 'getBrandByParams'])
            ->name('getbrandbyparams');
        Route::get('getvendorbyparams', [\Bangsamu\Master\Controllers\ApiController::class, 'getVendorByParams'])
            ->name('getvendorbyparams');
        Route::get('getappsbyparams', [\Bangsamu\Master\Controllers\ApiController::class, 'getAppsByParams'])
            ->name('getappsbyparams');
        Route::get('getcompanybyparams', [\Bangsamu\Master\Controllers\ApiController::class, 'getCompanyByParams'])
            ->name('getcompanybyparams');

        /*global select2 dari tabel*/
        Route::get('get{tabel}byparams', [\Bangsamu\Master\Controllers\ApiController::class, 'getTabelByParams'])
        ->name('get{tabel}byparams');

    });



Route::prefix('api')->group(function () {
        /*annotation untuk paraf dan signature*/
        Route::post('setAnnotationId', [\Bangsamu\Master\Controllers\ApiAnnotationController::class, 'setAnnotationId'])->name('setAnnotationId');
        Route::get('getcurrentuser', [\Bangsamu\Master\Controllers\ApiAnnotationController::class, 'getcurrentuser'])->name('getcurrentuser');
        Route::get('getSignature', [\Bangsamu\Master\Controllers\ApiAnnotationController::class, 'getSignature'])->name('getSignature');
        Route::get('getParaf', [\Bangsamu\Master\Controllers\ApiAnnotationController::class, 'getParaf'])->name('getParaf');
    });


Route::middleware(['web','auth'])->group(function () {
    //untuk kebutuhan app annotation get sesion
    Route::get('getcurrentuser', [\Bangsamu\Master\Controllers\ApiAnnotationController::class, 'getcurrentuser'])->middleware('auth')->name('getcurrentuser.async');
    Route::get('get-ip2',  [Bangsamu\Master\Controllers\MasterController::class, 'getIp'])
        ->name('get-ip2');
});


Route::get('session-crul',  [Bangsamu\Master\Controllers\MasterCrulController::class, 'masterCrul'])
    ->name('session-crul');



Route::middleware(['web'])->group(function () {
    Route::get('/optimize', function (Request $request) {

        try {
            $exitCode = $request->clear ? Artisan::call('optimize:clear') : Artisan::call('optimize');
            $output = Artisan::output();
        } catch (\Throwable $e) {
            $exitCode = 1;
            $output = $e->getMessage();
        }

        if (function_exists('opcache_reset')) {
            $opcacheReset = @opcache_reset();
            $output .= "\nOpcache reset: " . ($opcacheReset ? 'success' : 'failed');
        }

        if ($exitCode == 0) {
            return "<pre>Optimize successfully $output</pre>";
        } else {
            return "<pre>Optimize failed $output</pre>";
        }
        return "<pre>$output</pre>";

    });

    Route::get('/composer', function (Request $request) {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '-1');

        $composerBin = 'composer';
        if (is_executable('/usr/bin/composer')) {
            $composerBin = '/usr/bin/composer';
        } elseif (is_executable('/usr/local/bin/composer')) {
            $composerBin = '/usr/local/bin/composer';
        }

        $command = null;
        $action = null;

        if ($request->has('update')) {
            $action = 'update';
            $updateVal = $request->get('update');
            if ($updateVal === '1' || $updateVal === 'true' || empty($updateVal)) {
                $command = [$composerBin, 'update', '--no-interaction'];
            } else {
                $command = [$composerBin, 'update', $updateVal, '--no-interaction'];
            }
        } elseif ($request->has('install') || $request->has('instal')) {
            $action = 'install';
            $command = [$composerBin, 'install', '--no-interaction'];
        } elseif ($request->has('require')) {
            $action = 'require';
            $package = $request->get('require');
            $command = [$composerBin, 'require', $package, '--no-interaction'];
        } elseif ($request->has('remove')) {
            $action = 'remove';
            $package = $request->get('remove');
            $command = [$composerBin, 'remove', $package, '--no-interaction'];
        } elseif ($request->has('autoload') || $request->has('dump-autoload')) {
            $action = 'dump-autoload';
            $command = [$composerBin, 'dump-autoload', '--no-interaction'];
        }

        if (! $command) {
            if (! auth()->check()) {
                abort(404);
            }

            return "<pre>Composer Management Route\n\n" .
                "Available parameters:\n" .
                "  - ?update=1               : Run composer update\n" .
                "  - ?update=vendor/package  : Run composer update for specific package\n" .
                "  - ?install=1 / ?instal=1  : Run composer install\n" .
                "  - ?require=vendor/package : Run composer require vendor/package\n" .
                "  - ?remove=vendor/package  : Run composer remove vendor/package\n" .
                "  - ?autoload=1             : Run composer dump-autoload\n" .
                "</pre>";
        }

        $env = [
            'COMPOSER_HOME' => sys_get_temp_dir() . '/.composer',
            'COMPOSER_MEMORY_LIMIT' => '-1',
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
            'HOME' => getenv('HOME') ?: sys_get_temp_dir(),
        ];

        try {
            $process = new \Symfony\Component\Process\Process(
                $command,
                base_path(),
                $env,
                null,
                600
            );

            $process->run();
            $output = $process->getOutput() . $process->getErrorOutput();
            $exitCode = $process->getExitCode();
        } catch (\Throwable $e) {
            $output = $e->getMessage();
            $exitCode = 1;
        }

        $opcacheStatus = '';
        if (function_exists('opcache_reset')) {
            $opcacheReset = @opcache_reset();
            $opcacheStatus = "\nOpcache reset: " . ($opcacheReset ? 'success' : 'failed');
        }

        $statusText = ($exitCode === 0) ? 'successfully' : 'failed';
        $cmdString = implode(' ', $command);

        return "<pre>Composer Command: {$cmdString}\nStatus: {$statusText} (Exit Code: {$exitCode}){$opcacheStatus}\n\n" .
            htmlspecialchars($output, ENT_QUOTES, 'UTF-8') .
            "</pre>";
    });
});


Route::middleware(['web','auth'])->group(function () {
    Route::get('/profile', [Bangsamu\Master\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [Bangsamu\Master\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [Bangsamu\Master\Controllers\ProfileController::class, 'destroy'])->name('profile.destroy');
});

// =======================================

Route::middleware(['web'])->group(function () {
    Route::get('/dynamic-logo.png', [\Bangsamu\Master\Controllers\DynamicAssetController::class, 'logo'])->name('dynamic.logo');
    Route::get('/dynamic-favicon.ico', [\Bangsamu\Master\Controllers\DynamicAssetController::class, 'favicon'])->name('dynamic.favicon');
});

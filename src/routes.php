<?php

use Illuminate\Support\Facades\Route;

Route::get('mud-master', function () {
    // dd(config());
    $value = config('MasterConfig.main.APP_CODE');
    echo 'Hello from the master package!' . json_encode($value);
});

Route::get('mud-view', function () {
    return view('master::mud');
});




Route::middleware(['web','auth'])->prefix('master')->name('master.')->group(function () {
    // Route::get('user', [\App\Http\Controllers\Master\UserController::class, 'index'])->name('user.index');

    Route::resource('category', \Bangsamu\Master\Controllers\CategoryController::class);
    Route::post('category/import', [\Bangsamu\Master\Controllers\CategoryController::class, 'import'])->name('category.import');
    // Route::get('category/importtemplate', [\Bangsamu\Master\Controllers\CategoryController::class, 'importtemplate'])->name('category.importtemplate');

    Route::resource('company', \Bangsamu\Master\Controllers\CompanyController::class);
    Route::post('company/import', [\Bangsamu\Master\Controllers\CompanyController::class, 'import'])->name('project.import');

    // Route::resource('company', \App\Http\Controllers\Master\CompanyController::class);
    // Route::resource('department', \App\Http\Controllers\Master\DepartmentController::class);
    // Route::post('department/import', [\App\Http\Controllers\Master\DepartmentController::class, 'import'])->name('department.import');

    // Route::get('item_code/export', [\App\Http\Controllers\Master\ItemCodeController::class, 'export'])->name('item_code.export');
    // Route::get('item_code/importtemplate', [\App\Http\Controllers\Master\ItemCodeController::class, 'importtemplate'])->name('item_code.importtemplate');
    // Route::resource('item_code', \App\Http\Controllers\Master\ItemCodeController::class);
    // Route::post('item_code/import', [\App\Http\Controllers\Master\ItemCodeController::class, 'import'])->name('item_code.import');

    // Route::resource('item_group', \App\Http\Controllers\Master\ItemGroupController::class);
    // Route::post('item_group/import', [\App\Http\Controllers\Master\ItemGroupController::class, 'import'])->name('item_group.import');

    Route::resource('location', \Bangsamu\Master\Controllers\LocationController::class);
    Route::post('location/import', [\Bangsamu\Master\Controllers\LocationController::class, 'import'])->name('location.import');

    Route::resource('pca', \Bangsamu\Master\Controllers\PcaController::class);
    Route::post('pca/import', [\Bangsamu\Master\Controllers\PcaController::class, 'import'])->name('pca.import');

    Route::resource('project', \Bangsamu\Master\Controllers\ProjectController::class);
    Route::post('project/import', [\Bangsamu\Master\Controllers\ProjectController::class, 'import'])->name('project.import');

    // Route::resource('project2', \App\Http\Controllers\Master\ProjectController2::class);
    // Route::post('project2/import', [\App\Http\Controllers\Master\ProjectController2::class, 'import'])->name('project2.import');

    Route::resource('uom', \Bangsamu\Master\Controllers\UomController::class);
    Route::post('uom/import', [\Bangsamu\Master\Controllers\UomController::class, 'import'])->name('uom.import');

    // Route::resource('priority', \App\Http\Controllers\Master\PriorityController::class);


    // Route::resource('mcu', \Bangsamu\Master\Controllers\McuController::class);
    // Route::post('mcu/store-json', [\Bangsamu\Master\Controllers\McuController::class, 'storeJson'])->name('mcu.store.json');
    // Route::post('mcu/import', [\Bangsamu\Master\Controllers\McuController::class, 'import'])->name('mcu.import');

    Route::resource('employee', \Bangsamu\Master\Controllers\EmployeeController::class);
    Route::post('employee/import', [\Bangsamu\Master\Controllers\EmployeeController::class, 'import'])->name('employee.import');

    Route::get('get{table}/export', [\Bangsamu\Master\Controllers\ExportController::class, 'export'])->name('table.export');

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

Route::middleware(['web'])->group(function () {
    Route::get('get-ip2',  [Bangsamu\Master\Controllers\MasterController::class, 'getIp'])
        ->name('get-ip2');
});


Route::get('session-crul',  [Bangsamu\Master\Controllers\MasterCrulController::class, 'masterCrul'])
    ->name('session-crul');



// =======================================

/*
|--------------------------------------------------------------------------
| Master Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

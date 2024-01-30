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
            ->name('get-tabel');

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

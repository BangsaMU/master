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
        Route::get('get-ip',  [Bangsamu\Master\Controllers\MasterCrulController::class, 'getIp'])
            ->name('get-ip');

        Route::post('/sync-master/{tabel?}/{id?}', [\Bangsamu\Master\Controllers\MasterController::class, 'syncTabel'])
            ->name('sync-master');

        Route::get('/master-{tabel}/{id?}', [\Bangsamu\Master\Controllers\MasterController::class, 'tabel'])
            ->name('master-tabel');

        Route::get('/master-location/{id?}', [\Bangsamu\Master\Controllers\MasterController::class, 'location'])
            ->name('master-location');

        Route::get('/master-project/{id?}', [\Bangsamu\Master\Controllers\MasterController::class, 'project'])
            ->name('master-project');

        Route::get('/master-employee/{id?}', [\Bangsamu\Master\Controllers\MasterController::class, 'employee'])
            ->name('master-employee');

        Route::get('/master-item-code/{id?}', [\Bangsamu\Master\Controllers\MasterController::class, 'itemCode'])
            ->name('master-item-code');

        Route::get('/master-uom/{id?}', [\Bangsamu\Master\Controllers\MasterController::class, 'uom'])
            ->name('master-uom');
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

<?php

use Illuminate\Support\Facades\Route;

Route::get('mud-master', function () {
    $value = config('MasterConfig.main.APP_CODE');
    echo 'Hello from the master package!' . json_encode($value);
});

Route::get('mud-view', function () {
    return view('master::mud');
});

Route::get('getIp', function () {
    return view('master::mud');
});


Route::get('get-ip',  [Bangsamu\Master\Controllers\MasterCrulController::class, 'getIp'])
    ->name('get-ip');

Route::get('get-ip2',  [Bangsamu\Master\Controllers\MasterController::class, 'getIp'])
    ->name('get-ip2');


Route::middleware(['web'])->group(function () {
    Route::get('/session-cek', [Bangsamu\Master\Controllers\MasterController::class, 'sessionCek'])
        ->name('session-cek');

    Route::get('/session-set/{token}', [\Bangsamu\Master\Controllers\MasterController::class, 'sessionSet'])
        ->name('session-set');
    // Route::get('/session-set/{token}', [Bangsamu\Master\Controllers\MasterController::class, 'sessionSet'])
    //     ->name('session-set');
    Route::get('/session-unset/{token}', [Bangsamu\Master\Controllers\MasterController::class, 'sessionUnset'])
        ->name('session-unset');
    Route::get('/session-test/{credentials?}', [Bangsamu\Master\Controllers\MasterController::class, 'auth'])
        ->name('session-test');
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

Route::post('/sync-master/{tabel?}/{id?}', [\App\Http\Controllers\MasterController::class, 'syncTabel'])
    ->name('sync-master');

Route::get('/master-location/{id?}', [\App\Http\Controllers\MasterController::class, 'location'])
    ->name('master-location');

Route::get('/master-project/{id?}', [\App\Http\Controllers\MasterController::class, 'project'])
    ->name('master-project');

Route::get('/master-employee/{id?}', [\App\Http\Controllers\MasterController::class, 'employee'])
    ->name('master-employee');

Route::get('/master-item-code/{id?}', [\App\Http\Controllers\MasterController::class, 'itemCode'])
    ->name('master-item-code');

Route::get('/master-uom/{id?}', [\App\Http\Controllers\MasterController::class, 'uom'])
    ->name('master-uom');

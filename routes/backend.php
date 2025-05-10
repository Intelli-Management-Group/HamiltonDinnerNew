<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Api Routes
|--------------------------------------------------------------------------
|
| Here is where you can register Api routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your Api!
|
*/

Route::get('demo-backend', [App\Http\Controllers\Api\DinningController::class, 'demoGetRequestFromBackend']);

Route::post('login', [App\Http\Controllers\Api\DinningController::class, 'backendLogin']);
Route::get('/unauthorized', [App\Http\Controllers\Api\DinningController::class, 'unauthorized'])->name('unauthorized');
Route::get('logout', [App\Http\Controllers\Api\BackendUserController::class, 'logout']);
Route::get('/role/list', [App\Http\Controllers\Api\RoleController::class, 'list']);

Route::post('password-generate', [App\Http\Controllers\Api\BackendUserController::class, 'passwordGenerator']);

Route::middleware('auth:backend-api')->group(function () {

 
    Route::prefix('role/')->group(function () {
        
        Route::get('{id}/delete', [App\Http\Controllers\Api\RoleController::class, 'delete']);
        // Route::get('list', [App\Http\Controllers\Api\RoleController::class, 'list']);
        Route::get('{id}/get-by-id', [App\Http\Controllers\Api\RoleController::class, 'upsert']);
        
        Route::post('create', [App\Http\Controllers\Api\RoleController::class, 'upsert']);
        Route::get('tree', [App\Http\Controllers\Api\RoleController::class, 'getUserTree']);
        
        Route::post('sync', [App\Http\Controllers\Api\RoleController::class, 'syncPermission']);
        
    });
    
    Route::prefix('user/')->group(function () {
        
        Route::post('create', [App\Http\Controllers\Api\BackendUserController::class, 'upsert']);
        Route::get('{id}/delete', [App\Http\Controllers\Api\BackendUserController::class, 'delete']);
        Route::get('list', [App\Http\Controllers\Api\BackendUserController::class, 'list']);
        Route::get('{id}/get-by-id', [App\Http\Controllers\Api\BackendUserController::class, 'upsert']);
        
    });
    
    Route::prefix('permission/')->group(function () {
        
        Route::get('list', [App\Http\Controllers\Api\BackendUserController::class, 'permissionList']);

    });
    
});


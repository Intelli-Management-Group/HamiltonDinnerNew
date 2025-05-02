<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\CategoryDetailController;
use App\Http\Controllers\Api\ItemOptionController;
use App\Http\Controllers\Api\ItemDetailController; 
use App\Http\Controllers\Api\ItemPreferenceController;
use App\Http\Controllers\Api\RoomDetailController;
use App\Http\Controllers\Api\OrderController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Menu routes group
Route::prefix('menus')->group(function () {
    Route::get('/', [MenuController::class, 'index']);
    Route::post('/', [MenuController::class, 'store']);
    Route::get('/{id}', [MenuController::class, 'show']);
    Route::put('/{id}', [MenuController::class, 'update']);
    Route::delete('/bulk-delete', [MenuController::class, 'bulkDestroy']);
    Route::delete('/{id}', [MenuController::class, 'destroy']);
});

// Category routes group
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryDetailController::class, 'index']);
    Route::post('/', [CategoryDetailController::class, 'store']);
    Route::get('/{id}', [CategoryDetailController::class, 'show']);
    Route::put('/{id}', [CategoryDetailController::class, 'update']);
    Route::delete('/bulk-delete', [CategoryDetailController::class, 'bulkDestroy']);
    Route::delete('/{id}', [CategoryDetailController::class, 'destroy']);
});

// Item routes group
Route::prefix('item-options')->group(function () {
    Route::get('/', [ItemOptionController::class, 'index']);
    Route::post('/', [ItemOptionController::class, 'store']);
    Route::get('/{id}', [ItemOptionController::class, 'show']);
    Route::put('/{id}', [ItemOptionController::class, 'update']);
    Route::delete('/bulk-delete', [ItemOptionController::class, 'bulkDestroy']);
    Route::delete('/{id}', [ItemOptionController::class, 'destroy']);
});

// Item Details routes group
Route::prefix('item-details')->group(function () {
    Route::get('/', [ItemDetailController::class, 'index']);
    Route::post('/', [ItemDetailController::class, 'store']);
    Route::get('/{id}', [ItemDetailController::class, 'show']);
    Route::put('/{id}', [ItemDetailController::class, 'update']);
    Route::delete('/bulk-delete', [ItemDetailController::class, 'bulkDestroy']);
    Route::delete('/{id}', [ItemDetailController::class, 'destroy']);
    

});

// Preference routes group
Route::prefix('item-preferences')->group(function () {
    Route::get('/', [ItemPreferenceController::class, 'index']);
    Route::post('/', [ItemPreferenceController::class, 'store']);
    Route::get('/{id}', [ItemPreferenceController::class, 'show']);
    Route::put('/{id}', [ItemPreferenceController::class, 'update']);
    Route::delete('/bulk-delete', [ItemPreferenceController::class, 'bulkDestroy']);
    Route::delete('/{id}', [ItemPreferenceController::class, 'destroy']);
});

// Room routes group
Route::prefix('rooms')->group(function () {
    Route::get('/', [RoomDetailController::class, 'index']);
    Route::post('/', [RoomDetailController::class, 'store']);
    Route::get('/{id}', [RoomDetailController::class, 'show']);
    Route::put('/{id}', [RoomDetailController::class, 'update']);
    Route::delete('/bulk-delete', [RoomDetailController::class, 'bulkDestroy']);
    Route::delete('/{id}', [RoomDetailController::class, 'destroy']);
});

// Order routes
Route::get('reports', [OrderController::class, 'reportList']);


// Fallback route
Route::fallback(function(){
    return response()->json([
        'success' => false,
        'message' => 'Not Found',
    ], 404);
});
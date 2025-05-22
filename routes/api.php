<?php

use App\Http\Controllers\Api\Admin\AuthController;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\MenuController;
use App\Http\Controllers\Api\Admin\CategoryDetailController;
use App\Http\Controllers\Api\Admin\ItemOptionController;
use App\Http\Controllers\Api\Admin\ItemDetailController; 
use App\Http\Controllers\Api\Admin\ItemPreferenceController;
use App\Http\Controllers\Api\Admin\RoomDetailController;
use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Controllers\Api\DinningController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\SettingController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::post('login', [DinningController::class, 'login']); // APIToken

// Route::post('backend/ios/login', [DinningController::class, 'iosFormLogin']); // jwt auth , just for reference  , we are not using it actively

Route::group(['prefix' => 'admin'], function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('register', [AuthController::class, 'register'])->name('register');

});

// --------------------------------------------------------------------------

// routes related to adminpanel , ios form app and dynamic form app website 
// auth , roles, permissions are same for all these three



Route::group(['middleware' => 'APIToken'], function () {

    // Route::get('rooms-list', [DinningController::class, 'getRoomList']);
    // Route::post('item-list', [DinningController::class, 'getItemList']);
    // Route::post('demo-get-report-data', [DinningController::class, 'getCategoryWiseData']);
    // Route::post('demo-get-room-data', [DinningController::class, 'getRoomData']);
    // Route::post('print-order-data', [DinningController::class, 'printOrderData']);
    // Route::post('general-form-submit', [DinningController::class, 'saveForm']);
    // Route::post('edit-form', [DinningController::class, 'editGeneratedFormResponse']); // old working api stage 0
    // Route::post('get-report-data', [DinningController::class, 'getCategoryWiseDataDemo']);
    // Route::post('demo-order-list', [DinningController::class, 'getDemoOrderList']);
    // Route::post('demo-form-submit', [DinningController::class, 'saveForm1']);
    // Route::post('delete-form-attachment', [DinningController::class, 'deleteFormAttachment']);
    // Route::post('add-form-attachment', [DinningController::class, 'addAttachmentsToExistingForm']); 
    // Route::post('general-form-submit', [DinningController::class, 'saveForm']); // stage 0 , old pdf ui
    // Route::post('temp-form-save-by-user', [DinningController::class, 'saveTempFormByUser']); //Get-temp-form-list
    // Route::get('temp-form-template-download', [DinningController::class, 'getTempFormDownload']);
    // Route::post('save-temp-form-pdf', [DinningController::class, 'saveFormTempPdf']);
    
    Route::post('order-list', [DinningController::class, 'getOrderList']);
    Route::post('update-order', [DinningController::class, 'updateOrder']);
    Route::post('get-user-data', [DinningController::class, 'getUserData']);
    Route::post('send-email', [DinningController::class, 'sendEmail']);
    Route::post('form-details', [DinningController::class, 'getFormDetails']);
    
    Route::post('delete-form', [DinningController::class, 'deleteFormResponse']);
    Route::post('complete-log', [DinningController::class, 'completeFormLog']);
    Route::post('guest-order-list', [DinningController::class, 'getGuestOrderList']);

    Route::post('list-forms', [DinningController::class, 'getGeneratedForms']);
    
    Route::post('general-form-submit-phase1', [DinningController::class, 'saveFormPhase1']); // new pdf with images stage 1
    Route::post('edit-form-phase1', [DinningController::class, 'editGeneratedFormResponsePhase1']); // new api stage 1
    Route::post('add-form-attachment-phase1', [DinningController::class, 'addAttachmentsToExistingFormPhase1']); // new api stage 1
    Route::post('delete-form-attachment-phase1', [DinningController::class, 'deleteFormAttachmentPhase1']); // new api stage 1

    Route::post('get-report-data', [DinningController::class, 'getCategoryWiseDataDemo']);

    Route::get('get-move-in-summary-values', [DinningController::class, 'getMoveInSummaryValues']);
    
    Route::post('get-charges-report', [DinningController::class, 'reportData']);
    Route::post('print-combined-order-data', [DinningController::class, 'printOrderDataTemp']);

    Route::post('temp-get-charges-report', [DinningController::class, 'reportDataTemp2']);

    Route::post('multi-order-update', [DinningController::class, 'updateOrderBulk']);
    
});


Route::group(['prefix' => 'admin', 'middleware' => 'auth:api'], function () {

    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('me', [AuthController::class, 'me']);
    Route::post('refresh', [AuthController::class, 'refresh']);

    Route::post('temp-send-email', [DinningController::class, 'tempSendMail']);
    Route::post('temp-form-response-list' , [DinningController::class, 'getTempFormResponseList']);
    Route::get('get-temp-form-list', [DinningController::class, 'getTempFormTypesList']);
    Route::post('temp-form-save', [DinningController::class, 'saveTempForm']);
    Route::get('demo-form-fields-by-id/{id}', [DinningController::class, 'getDynamicFormDemoDataById']);
    Route::get('temp-get-user-data', [DinningController::class, 'getTempUserData']);
    Route::get('{id}/temp-form-response-delete', [DinningController::class, 'deleteTempFormResponse']);

    //website routes
    Route::post('temp-form-save-by-user', [DinningController::class, 'saveTempFormByUser']); //Get-temp-form-list
    Route::get('temp-form-type/{id}/delete', [DinningController::class, 'deleteTempFormType']);
    Route::get('temp-form-type-list', [DinningController::class, 'tempFormTypeList']);
    Route::get('{id}/temp-form-type-by-id', [DinningController::class, 'tempFormTypeById']);
    Route::post('edit-temp-form', [DinningController::class, 'editGeneratedTempFormResponse']);
    Route::post('delete-temp-form-attachment', [DinningController::class, 'deleteTempFormAttachment']);
    Route::post('add-temp-form-attachment', [DinningController::class, 'addAttachmentsToExistingTempForm']);
    Route::post('temp-form-details', [DinningController::class, 'getTempFormDetails']);

    // --------------------------------------------------------------------

    // custom admin panel routes

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

    // Item option routes group
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


    //  roles routes group
    Route::prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::post('/', [RoleController::class, 'store']);
        Route::get('/{id}', [RoleController::class, 'show']);
        Route::put('/{id}', [RoleController::class, 'update']);
        Route::delete('/bulk-delete', [RoleController::class, 'bulkDestroy']);
        Route::delete('/{id}', [RoleController::class, 'destroy']);

        Route::get('tree', [RoleController::class, 'getUserTree']);
        
        Route::post('sync', [RoleController::class, 'syncPermission']);
    });

    //  permissions routes group
    Route::prefix('permissions')->group(function () {
        Route::get('/', [PermissionController::class, 'index']);
        // Route::post('/', [PermissionController::class, 'store']);
        // Route::get('/{id}', [PermissionController::class, 'show']);
        // Route::put('/{id}', [PermissionController::class, 'update']);
        // Route::delete('/bulk-delete', [PermissionController::class, 'bulkDestroy']);
        // Route::delete('/{id}', [PermissionController::class, 'destroy']);
    });

    // User routes group
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/bulk-delete', [UserController::class, 'bulkDestroy']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
    });

    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingController::class, 'index']);
        Route::post('/', [SettingController::class, 'store']);
        Route::get('/{id}', [SettingController::class, 'show']);
        Route::put('/', [SettingController::class, 'update']);
        Route::delete('/{id}', [SettingController::class, 'destroy']);
        Route::post('/bulk-delete', [SettingController::class, 'bulkDestroy']);
    });

    // Order routes (moved inside admin group)
    Route::get('reports', [OrderController::class, 'reportList']);
    
});

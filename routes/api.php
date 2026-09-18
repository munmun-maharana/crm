<?php

use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\MobileCrmController;
use App\Http\Controllers\Api\MobileDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')->group(function () {
    Route::post('/login', [MobileAuthController::class, 'login']);

    Route::middleware('api.token')->group(function () {
        Route::get('/me', [MobileAuthController::class, 'me']);
        Route::post('/logout', [MobileAuthController::class, 'logout']);
        Route::get('/dashboard', [MobileDashboardController::class, 'index']);
        Route::get('/crm', [MobileDashboardController::class, 'crm']);
        Route::post('/crm', [MobileCrmController::class, 'store'])->defaults('pipeline', 'crm');
        Route::get('/crm/{customer}', [MobileCrmController::class, 'show'])->defaults('pipeline', 'crm');
        Route::put('/crm/{customer}', [MobileCrmController::class, 'update'])->defaults('pipeline', 'crm');
        Route::post('/crm/{customer}/activities', [MobileCrmController::class, 'addActivity'])->defaults('pipeline', 'crm');
        Route::get('/operations', [MobileDashboardController::class, 'operations']);
        Route::post('/operations', [MobileCrmController::class, 'store'])->defaults('pipeline', 'operations');
        Route::get('/operations/inventory-types', [MobileDashboardController::class, 'inventoryTypes']);
        Route::post('/operations/{customer}/activation-requests/start', [MobileDashboardController::class, 'startActivation']);
        Route::post('/operations/{customer}/activation-requests', [MobileDashboardController::class, 'storeActivation']);
        Route::get('/operations/{customer}', [MobileCrmController::class, 'show'])->defaults('pipeline', 'operations');
        Route::put('/operations/{customer}', [MobileCrmController::class, 'update'])->defaults('pipeline', 'operations');
        Route::post('/operations/{customer}/activities', [MobileCrmController::class, 'addActivity'])->defaults('pipeline', 'operations');
        Route::get('/inventory', [MobileDashboardController::class, 'inventory']);
        Route::get('/inventory/{inventory}/history', [MobileDashboardController::class, 'inventoryHistory']);
    });
});

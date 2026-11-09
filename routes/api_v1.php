<?php

use App\Http\Controllers\Api\V1\AuthController as ApiAuthController;
use App\Http\Controllers\Api\V1\CustomerController as ApiCustomerController;
use App\Http\Controllers\Api\V1\DashboardController as ApiDashboardController;
use App\Http\Controllers\Api\V1\SettingsController as ApiSettingsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingSlotController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\NoteTemplateController;
use App\Http\Controllers\OperationSettingController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WhatsappSettingController;
use Illuminate\Support\Facades\Route;

/**
 * Mobile API v1 routes.
 * Connected to existing database, sharing the exact business logic and models.
 */
Route::post('/login', [ApiAuthController::class, 'login']);

Route::middleware(['api.token', 'api.mobile_json'])->group(function () {
    // Current user & password
    Route::get('/me', [ApiAuthController::class, 'me']);
    Route::post('/logout', [ApiAuthController::class, 'logout']);
    Route::put('/change-password', [AuthController::class, 'updatePassword']);

    // Dashboard with charts & summary stats
    Route::get('/dashboard', [ApiDashboardController::class, 'index']);

    // Customers / CRM / Team Ops
    Route::get('/customers', [ApiCustomerController::class, 'index']);
    Route::get('/customers/meta', [ApiCustomerController::class, 'meta']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::get('/customers/{customer}', [ApiCustomerController::class, 'show']);
    Route::put('/customers/{customer}', [CustomerController::class, 'update']);
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy']);

    // Customer Actions
    Route::post('/customers/{customer}/activities', [CustomerController::class, 'addActivity']);
    Route::post('/customers/{customer}/assign-operations', [CustomerController::class, 'assignToOperations']);
    Route::post('/customers/{customer}/auto/{channel}', [CustomerController::class, 'autoAction']);
    Route::post('/customers/{customer}/compose', [CustomerController::class, 'compose']);

    // Bulk Customer operations
    Route::post('/customers/bulk-map', [CustomerController::class, 'bulkMap']);
    Route::post('/customers/bulk-whatsapp', [CustomerController::class, 'bulkWhatsapp']);
    Route::post('/customers/bulk-email', [CustomerController::class, 'bulkEmail']);

    // Requests pipeline
    Route::get('/requests', [ApiCustomerController::class, 'requests']);

    // Booking Slots
    Route::get('/booking-slots', [BookingSlotController::class, 'index']);
    Route::post('/booking-slots/{bookingSlot}/book', [BookingSlotController::class, 'book']);

    // Excel import / export / demo
    Route::get('/upload/demo', [UploadController::class, 'demo']);
    Route::get('/upload/export', [UploadController::class, 'export']);
    Route::post('/upload', [UploadController::class, 'store']);

    // Admin-only Settings & User Management
    Route::middleware('admin')->group(function () {
        Route::get('/settings', [ApiSettingsController::class, 'index']);

        // Settings - Booking slots
        Route::get('/settings/booking-slots', [ApiSettingsController::class, 'bookingSlots']);
        Route::post('/settings/booking-slots', [BookingSlotController::class, 'store']);
        Route::put('/settings/booking-slots/setting', [BookingSlotController::class, 'updateSetting']);
        Route::put('/settings/booking-slots/{bookingSlot}', [BookingSlotController::class, 'update']);
        Route::delete('/settings/booking-slots/date', [BookingSlotController::class, 'destroyDate']);
        Route::delete('/settings/booking-slots/{bookingSlot}', [BookingSlotController::class, 'destroy']);

        // Settings - Custom Fields
        Route::post('/settings/fields', [FieldController::class, 'store']);
        Route::post('/settings/fields/reorder', [FieldController::class, 'reorder']);
        Route::put('/settings/fields/{field}', [FieldController::class, 'update']);
        Route::delete('/settings/fields/{field}', [FieldController::class, 'destroy']);

        // Settings - Note Templates
        Route::post('/settings/note-templates', [NoteTemplateController::class, 'store']);
        Route::put('/settings/note-templates/{noteTemplate}', [NoteTemplateController::class, 'update']);
        Route::delete('/settings/note-templates/{noteTemplate}', [NoteTemplateController::class, 'destroy']);

        // Settings - Operation Setting & WhatsApp Setting
        Route::put('/settings/operation-setting', [OperationSettingController::class, 'update']);
        Route::put('/settings/whatsapp-setting', [WhatsappSettingController::class, 'update']);

        // User Management
        Route::get('/users', [ApiSettingsController::class, 'users']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });
});


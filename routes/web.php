<?php

use App\Http\Controllers\ActivationRequestController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingSlotController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryTypeController;
use App\Http\Controllers\NoteTemplateController;
use App\Http\Controllers\PincodeController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\OperationSettingController;
use App\Http\Controllers\WhatsappSettingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::put('/change-password', [AuthController::class, 'updatePassword'])->name('password.update');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/call-crm', [CustomerController::class, 'callCrm'])->name('call-crm.index');
    Route::get('/operations', [CustomerController::class, 'operations'])->name('operations.index');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::post('/customers/bulk-map', [CustomerController::class, 'bulkMap'])->name('customers.bulk-map');
    Route::post('/customers/bulk-whatsapp', [CustomerController::class, 'bulkWhatsapp'])->name('customers.bulk-whatsapp');
    Route::post('/customers/bulk-email', [CustomerController::class, 'bulkEmail'])->name('customers.bulk-email');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    Route::post('/customers/{customer}/assign-operations', [CustomerController::class, 'assignToOperations'])->name('customers.assign-operations');
    Route::post('/customers/{customer}/activities', [CustomerController::class, 'addActivity'])->name('customers.activities');
    Route::post('/customers/{customer}/auto/{channel}', [CustomerController::class, 'autoAction'])->name('customers.auto');
    Route::post('/customers/{customer}/compose', [CustomerController::class, 'compose'])->name('customers.compose');

    Route::get('/requests', [CustomerController::class, 'requests'])->name('requests.index');

    Route::get('/activation', fn () => redirect()->route('dashboard'))->name('activation.index');
    Route::get('/inventory', fn () => redirect()->route('dashboard'))->name('inventory.index');

    Route::get('/booking-slots', [BookingSlotController::class, 'index'])->name('booking-slots.index');
    Route::post('/booking-slots/{bookingSlot}/book', [BookingSlotController::class, 'book'])->name('booking-slots.book');

    Route::get('/pincodes', [PincodeController::class, 'index'])->name('pincodes.index');
    Route::post('/pincodes', [PincodeController::class, 'store'])->name('pincodes.store');
    Route::post('/pincodes/bulk', [PincodeController::class, 'bulkAction'])->name('pincodes.bulk');
    Route::get('/pincodes/export', [PincodeController::class, 'export'])->name('pincodes.export');
    Route::get('/pincodes/demo', [PincodeController::class, 'demo'])->name('pincodes.demo');
    Route::post('/pincodes/import', [PincodeController::class, 'import'])->name('pincodes.import');
    Route::put('/pincodes/{pincode}', [PincodeController::class, 'update'])->name('pincodes.update');
    Route::delete('/pincodes/{pincode}', [PincodeController::class, 'destroy'])->name('pincodes.destroy');

    Route::get('/upload', [UploadController::class, 'index'])->name('upload.index');
    Route::get('/upload/demo', [UploadController::class, 'demo'])->name('upload.demo');
    Route::get('/upload/export', [UploadController::class, 'export'])->name('upload.export');
    Route::post('/upload', [UploadController::class, 'store'])->name('upload.store');

    Route::middleware('admin')->group(function () {
        Route::get('/crm-setting', [SettingsController::class, 'index'])->name('settings.index');
        Route::get('/setting', fn () => redirect()->route('settings.index'))->name('setting');

        Route::get('/fields', fn () => redirect()->route('settings.index', ['tab' => 'fields']))->name('fields.index');
        Route::post('/fields', [FieldController::class, 'store'])->name('fields.store');
        Route::post('/fields/reorder', [FieldController::class, 'reorder'])->name('fields.reorder');
        Route::put('/fields/{field}', [FieldController::class, 'update'])->name('fields.update');
        Route::delete('/fields/{field}', [FieldController::class, 'destroy'])->name('fields.destroy');

        Route::get('/note-templates', fn () => redirect()->route('settings.index', ['tab' => 'notes']))->name('note-templates.index');
        Route::post('/note-templates', [NoteTemplateController::class, 'store'])->name('note-templates.store');
        Route::put('/note-templates/{noteTemplate}', [NoteTemplateController::class, 'update'])->name('note-templates.update');
        Route::delete('/note-templates/{noteTemplate}', [NoteTemplateController::class, 'destroy'])->name('note-templates.destroy');

        Route::get('/operation-setting', function () {
            $tab = request('tab') === 'fields' ? 'op-fields' : 'op-assign';

            return redirect()->route('settings.index', ['tab' => $tab]);
        })->name('operation-settings.index');
        Route::put('/operation-setting', [OperationSettingController::class, 'update'])->name('operation-settings.update');
        Route::put('/whatsapp-setting', [WhatsappSettingController::class, 'update'])->name('whatsapp-settings.update');

        Route::post('/booking-slots', [BookingSlotController::class, 'store'])->name('booking-slots.store');
        Route::put('/booking-slots/setting', [BookingSlotController::class, 'updateSetting'])->name('booking-slots.setting');
        Route::put('/booking-slots/{bookingSlot}', [BookingSlotController::class, 'update'])->name('booking-slots.update');
        Route::delete('/booking-slots/date', [BookingSlotController::class, 'destroyDate'])->name('booking-slots.destroy-date');
        Route::delete('/booking-slots/{bookingSlot}', [BookingSlotController::class, 'destroy'])->name('booking-slots.destroy');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});

<?php

use App\Http\Controllers\ActivationRequestController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryTypeController;
use App\Http\Controllers\NoteTemplateController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\OperationSettingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/operations', [CustomerController::class, 'operations'])->name('operations.index');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::post('/customers/bulk-map', [CustomerController::class, 'bulkMap'])->name('customers.bulk-map');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    Route::post('/customers/{customer}/assign-operations', [CustomerController::class, 'assignToOperations'])->name('customers.assign-operations');
    Route::post('/customers/{customer}/activities', [CustomerController::class, 'addActivity'])->name('customers.activities');
    Route::post('/customers/{customer}/auto/{channel}', [CustomerController::class, 'autoAction'])->name('customers.auto');
    Route::post('/customers/{customer}/compose', [CustomerController::class, 'compose'])->name('customers.compose');
    Route::post('/customers/{customer}/activation-requests', [ActivationRequestController::class, 'store'])->name('customers.activation-requests');
    Route::post('/customers/{customer}/activation-requests/start', [ActivationRequestController::class, 'start'])->name('customers.activation-requests.start');

    Route::get('/requests', [CustomerController::class, 'requests'])->name('requests.index');

    Route::get('/activation', [ActivationRequestController::class, 'index'])->name('activation.index');
    Route::put('/activation/{activation}', [ActivationRequestController::class, 'update'])->name('activation.update');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/user-stock', [InventoryController::class, 'userStock'])->name('inventory.user-stock');
    Route::post('/inventory/shift-stock', [InventoryController::class, 'shiftStock'])->name('inventory.shift-stock');
    Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::get('/inventory/{inventory}/history', [InventoryController::class, 'history'])->name('inventory.history');
    Route::put('/inventory/{inventory}', [InventoryController::class, 'update'])->name('inventory.update');
    Route::delete('/inventory/{inventory}', [InventoryController::class, 'destroy'])->name('inventory.destroy');

    Route::get('/upload', [UploadController::class, 'index'])->name('upload.index');
    Route::get('/upload/demo', [UploadController::class, 'demo'])->name('upload.demo');
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

        Route::post('/inventory-types', [InventoryTypeController::class, 'store'])->name('inventory-types.store');
        Route::put('/inventory-types/{inventoryType}', [InventoryTypeController::class, 'update'])->name('inventory-types.update');
        Route::delete('/inventory-types/{inventoryType}', [InventoryTypeController::class, 'destroy'])->name('inventory-types.destroy');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});

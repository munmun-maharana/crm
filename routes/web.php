<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\NoteTemplateController;
use App\Http\Controllers\UploadController;
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
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    Route::post('/customers/{customer}/activities', [CustomerController::class, 'addActivity'])->name('customers.activities');
    Route::post('/customers/{customer}/auto/{channel}', [CustomerController::class, 'autoAction'])->name('customers.auto');
    Route::post('/customers/{customer}/compose', [CustomerController::class, 'compose'])->name('customers.compose');

    Route::get('/requests', [CustomerController::class, 'requests'])->name('requests.index');

    Route::get('/fields', [FieldController::class, 'index'])->name('fields.index');
    Route::post('/fields', [FieldController::class, 'store'])->name('fields.store');
    Route::post('/fields/reorder', [FieldController::class, 'reorder'])->name('fields.reorder');
    Route::put('/fields/{field}', [FieldController::class, 'update'])->name('fields.update');
    Route::delete('/fields/{field}', [FieldController::class, 'destroy'])->name('fields.destroy');

    Route::get('/note-templates', [NoteTemplateController::class, 'index'])->name('note-templates.index');
    Route::post('/note-templates', [NoteTemplateController::class, 'store'])->name('note-templates.store');
    Route::put('/note-templates/{noteTemplate}', [NoteTemplateController::class, 'update'])->name('note-templates.update');
    Route::delete('/note-templates/{noteTemplate}', [NoteTemplateController::class, 'destroy'])->name('note-templates.destroy');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::get('/upload', [UploadController::class, 'index'])->name('upload.index');
    Route::get('/upload/demo', [UploadController::class, 'demo'])->name('upload.demo');
    Route::post('/upload', [UploadController::class, 'store'])->name('upload.store');
});

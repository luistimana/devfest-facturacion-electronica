<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SunatSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/', [LoginController::class, 'show'])->name('login');
    Route::get('/login', [LoginController::class, 'show']);
    Route::post('/login', [LoginController::class, 'authenticate'])->name('login.authenticate');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/settings/sunat', [SunatSettingsController::class, 'edit'])->name('settings.sunat.edit');
    Route::put('/settings/sunat', [SunatSettingsController::class, 'update'])->name('settings.sunat.update');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::middleware('sunat.setup')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');
        Route::get('/sales/create', [SalesController::class, 'create'])->name('sales.create');
        Route::post('/sales', [SalesController::class, 'store'])->name('sales.store');
        Route::get('/sales/{document}', [SalesController::class, 'show'])->name('sales.show');
        Route::get('/sales/{document}/pdf', [SalesController::class, 'pdf'])->name('sales.pdf');
        Route::post('/sales/{document}/send', [SalesController::class, 'send'])->name('sales.send');
        Route::post('/sales/{document}/consult', [SalesController::class, 'consult'])->name('sales.consult');
        Route::get('/sales/{document}/download/{artifact}', [SalesController::class, 'download'])->name('sales.download');
    });
});

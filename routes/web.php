<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\OutletController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\SaleController;
use App\Http\Controllers\Web\StockAdjustmentController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store')
        ->middleware('role:owner,manager');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit')
        ->middleware('role:owner,manager');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update')
        ->middleware('role:owner,manager');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy')
        ->middleware('role:owner,manager');

    Route::get('/outlets', [OutletController::class, 'index'])->name('outlets.index');
    Route::post('/outlets', [OutletController::class, 'store'])->name('outlets.store')
        ->middleware('role:owner,manager');
    Route::get('/outlets/{outlet}/edit', [OutletController::class, 'edit'])->name('outlets.edit')
        ->middleware('role:owner,manager');
    Route::put('/outlets/{outlet}', [OutletController::class, 'update'])->name('outlets.update')
        ->middleware('role:owner,manager');
    Route::delete('/outlets/{outlet}', [OutletController::class, 'destroy'])->name('outlets.destroy')
        ->middleware('role:owner,manager');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store')
        ->middleware('role:owner,manager');
    Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit')
        ->middleware('role:owner,manager');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update')
        ->middleware('role:owner,manager');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy')
        ->middleware('role:owner,manager');

    Route::get('/stock-adjustments', [StockAdjustmentController::class, 'index'])->name('stock-adjustments.index')
        ->middleware('role:owner,manager');
    Route::post('/stock-adjustments', [StockAdjustmentController::class, 'store'])->name('stock-adjustments.store')
        ->middleware('role:owner,manager');

    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('/sales/create', [SaleController::class, 'create'])->name('sales.create');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::post('/sales/{sale}/void', [SaleController::class, 'void'])->name('sales.void')
        ->middleware('role:owner,manager');
    Route::post('/sales/{sale}/refund', [SaleController::class, 'refund'])->name('sales.refund')
        ->middleware('role:owner,manager');
});

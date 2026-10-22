<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\DiningTableController;
use App\Http\Controllers\Web\IngredientController;
use App\Http\Controllers\Web\IngredientMovementController;
use App\Http\Controllers\Web\KdsController;
use App\Http\Controllers\Web\OpnameController;
use App\Http\Controllers\Web\RecipeController;
use App\Http\Controllers\Web\WarehouseController;
use App\Http\Controllers\Web\QrGuestController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\OutletController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\SaleController;
use App\Http\Controllers\Web\StockAdjustmentController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

// Public guest ordering page reached by scanning a table QR code.
Route::get('/qr/{code}', [QrGuestController::class, 'show'])->name('qr.guest');

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

    Route::get('/dining-tables', [DiningTableController::class, 'index'])->name('dining-tables.index');
    Route::post('/dining-tables', [DiningTableController::class, 'store'])->name('dining-tables.store')
        ->middleware('role:owner,manager');
    Route::get('/dining-tables/{table}/edit', [DiningTableController::class, 'edit'])->name('dining-tables.edit')
        ->middleware('role:owner,manager');
    Route::put('/dining-tables/{table}', [DiningTableController::class, 'update'])->name('dining-tables.update')
        ->middleware('role:owner,manager');
    Route::post('/dining-tables/{table}/regenerate', [DiningTableController::class, 'regenerate'])->name('dining-tables.regenerate')
        ->middleware('role:owner,manager');

    Route::get('/inventory/ingredients', [IngredientController::class, 'index'])->name('inventory.ingredients')
        ->middleware('role:owner,manager');
    Route::post('/inventory/ingredients', [IngredientController::class, 'store'])->name('inventory.ingredients.store')
        ->middleware('role:owner,manager');
    Route::get('/inventory/warehouses', [WarehouseController::class, 'index'])->name('inventory.warehouses')
        ->middleware('role:owner,manager');
    Route::post('/inventory/warehouses', [WarehouseController::class, 'store'])->name('inventory.warehouses.store')
        ->middleware('role:owner,manager');
    Route::get('/inventory/recipes', [RecipeController::class, 'index'])->name('inventory.recipes')
        ->middleware('role:owner,manager');
    Route::post('/inventory/recipes', [RecipeController::class, 'store'])->name('inventory.recipes.store')
        ->middleware('role:owner,manager');
    Route::delete('/inventory/recipes/{recipe}', [RecipeController::class, 'destroy'])->name('inventory.recipes.destroy')
        ->middleware('role:owner,manager');
    Route::get('/inventory/movements', [IngredientMovementController::class, 'index'])->name('inventory.movements')
        ->middleware('role:owner,manager');
    Route::post('/inventory/movements', [IngredientMovementController::class, 'store'])->name('inventory.movements.store')
        ->middleware('role:owner,manager');
    Route::post('/inventory/transfers', [IngredientMovementController::class, 'transfer'])->name('inventory.transfers.store')
        ->middleware('role:owner,manager');
    Route::get('/inventory/opnames', [OpnameController::class, 'index'])->name('inventory.opnames')
        ->middleware('role:owner,manager');
    Route::post('/inventory/opnames', [OpnameController::class, 'store'])->name('inventory.opnames.store')
        ->middleware('role:owner,manager');

    Route::get('/kds', [KdsController::class, 'index'])->name('kds.index');
    Route::post('/kds/{order}/status', [KdsController::class, 'updateStatus'])->name('kds.status');
    Route::get('/kds/{order}/convert', [KdsController::class, 'convertForm'])->name('kds.convert.form');
    Route::post('/kds/{order}/convert', [KdsController::class, 'convert'])->name('kds.convert');
});

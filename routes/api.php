<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\DiningTableController;
use App\Http\Controllers\Api\V1\IngredientController;
use App\Http\Controllers\Api\V1\IngredientMovementController;
use App\Http\Controllers\Api\V1\OpnameController;
use App\Http\Controllers\Api\V1\RecipeController;
use App\Http\Controllers\Api\V1\WarehouseController;
use App\Http\Controllers\Api\V1\QrOrderController;
use App\Http\Controllers\Api\V1\TableOrderController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OwnerSummaryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\ShiftController;
use Illuminate\Support\Facades\Route;

// API skeleton for the future mobile cashier. MVP payments only: cash, edc.
Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    // QR guest ordering: public, no login, no app install.
    Route::get('/qr/{code}', [QrOrderController::class, 'show']);
    Route::post('/qr/{code}/orders', [QrOrderController::class, 'store']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/sales', [SaleController::class, 'index']);
        Route::post('/sales', [SaleController::class, 'store']);
        Route::get('/attendance/open', [AttendanceController::class, 'open']);
        Route::get('/attendance/me', [AttendanceController::class, 'myIndex']);
        Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
        Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
        Route::get('/attendance', [AttendanceController::class, 'index']);
        Route::get('/shifts/my', [ShiftController::class, 'myIndex']);
        Route::get('/shifts', [ShiftController::class, 'index']);
        Route::post('/shifts', [ShiftController::class, 'store']);
        Route::get('/owner/summary', [OwnerSummaryController::class, 'show']);
        Route::get('/tables', [DiningTableController::class, 'index']);
        Route::post('/tables', [DiningTableController::class, 'store']);
        Route::put('/tables/{table}', [DiningTableController::class, 'update']);
        Route::post('/tables/{table}/regenerate', [DiningTableController::class, 'regenerate']);
        Route::get('/table-orders', [TableOrderController::class, 'index']);
        Route::post('/table-orders/{order}/status', [TableOrderController::class, 'updateStatus']);
        Route::post('/table-orders/{order}/convert', [TableOrderController::class, 'convert']);
        // Complete inventory (supervisor-only, tenant-scoped).
        Route::get('/ingredients', [IngredientController::class, 'index']);
        Route::get('/ingredients/{ingredient}', [IngredientController::class, 'show']);
        Route::post('/ingredients', [IngredientController::class, 'store']);
        Route::put('/ingredients/{ingredient}', [IngredientController::class, 'update']);
        Route::delete('/ingredients/{ingredient}', [IngredientController::class, 'destroy']);
        Route::get('/warehouses', [WarehouseController::class, 'index']);
        Route::post('/warehouses', [WarehouseController::class, 'store']);
        Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update']);
        Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy']);
        Route::get('/recipes', [RecipeController::class, 'index']);
        Route::post('/recipes', [RecipeController::class, 'store']);
        Route::put('/recipes/{recipe}', [RecipeController::class, 'update']);
        Route::delete('/recipes/{recipe}', [RecipeController::class, 'destroy']);
        Route::get('/ingredient-movements', [IngredientMovementController::class, 'index']);
        Route::post('/ingredient-movements', [IngredientMovementController::class, 'store']);
        Route::post('/ingredient-transfers', [IngredientMovementController::class, 'transfer']);
        Route::get('/opnames', [OpnameController::class, 'index']);
        Route::post('/opnames', [OpnameController::class, 'store']);
    });
});

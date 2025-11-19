<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\WarehouseStockController;
use App\Http\Controllers\DriverStockController;
use App\Http\Controllers\AssignStockController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\AdminStatsController;

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API is running',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// ============================================
// AUTHENTICATION ROUTES
// ============================================
Route::post('/login', [AuthController::class, 'login']); // General login (admin or driver)
Route::post('/driver/login', [AuthController::class, 'driverLogin']); // Driver specific login
Route::post('/admin/login', [AuthController::class, 'adminLogin']); // Admin specific login
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

// ============================================
// CATEGORIES ROUTES (Admin only)
// ============================================
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
});

// ============================================
// PRODUCTS ROUTES (Admin only)
// ============================================
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
});

// ============================================
// WAREHOUSE STOCK ROUTES (Admin only)
// ============================================
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/warehouse-stock', [WarehouseStockController::class, 'index']);
    Route::post('/warehouse-stock/update', [WarehouseStockController::class, 'update']);
});

// ============================================
// DRIVER STOCK ROUTES
// ============================================
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/drivers/{id}/stock', [DriverStockController::class, 'show']);
    Route::post('/assign-stock', [AssignStockController::class, 'store']);
});

// Driver viewing their own stock
Route::middleware(['auth:sanctum', 'driver'])->group(function () {
    Route::get('/driver/my-stock', [DriverStockController::class, 'myStock']);
});

// ============================================
// SALES ROUTES (Driver only)
// ============================================
Route::middleware(['auth:sanctum', 'driver'])->group(function () {
    Route::post('/sales', [SaleController::class, 'store']);
    Route::get('/sales', [SaleController::class, 'index']); // Driver's own sales
    Route::get('/sales/{id}', [SaleController::class, 'show']);
    Route::get('/sales/{id}/invoice', [SaleController::class, 'invoice']);
});

// ============================================
// ADMIN REPORTS ROUTES (Admin only)
// ============================================
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/admin/stats', [AdminStatsController::class, 'index']);
});

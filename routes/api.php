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
use App\Http\Controllers\DriverController;
use App\Http\Controllers\DriverStatsController;
use App\Http\Controllers\AdminSalesController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomLayoutController;
use App\Http\Controllers\ProductDimensionController;
use App\Http\Controllers\RoomVisualizationController;
use App\Http\Controllers\DriverDashboardController;
use App\Http\Controllers\StockOrderController;
use App\Http\Controllers\FcmTokenController;

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

// Password reset routes (no auth required)
Route::post('/driver/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/driver/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    // FCM Token Management
    Route::post('/fcm-token', [FcmTokenController::class, 'store']);
    Route::delete('/fcm-token', [FcmTokenController::class, 'destroy']);
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
// WAREHOUSE STOCK & ROOM STOCK ROUTES (Admin only)
// ============================================
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/warehouse-stock', [WarehouseStockController::class, 'index']);
    Route::post('/warehouse-stock/update', [WarehouseStockController::class, 'update']);
    Route::post('/warehouse-stock/place', [WarehouseStockController::class, 'placeIntoRoom']);
    
    // Storage Suggestions
    Route::get('/warehouse-stock/{product_id}/suggest-storage', [WarehouseStockController::class, 'suggestStorage']);
    Route::post('/warehouse-stock/apply-suggestion', [WarehouseStockController::class, 'applySuggestion']);
    Route::get('/warehouse-stock/pending-suggestions', [WarehouseStockController::class, 'pendingSuggestions']);

    // Room stock (per-room inventory)
    Route::get('/rooms/{id}/stock', [RoomController::class, 'stock']);
    Route::get('/products/{id}/room-availability', [RoomController::class, 'productRoomAvailability']);
});

// ============================================
// DRIVER STOCK ROUTES
// ============================================
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/drivers/{id}/stock', [DriverStockController::class, 'show']);
    Route::post('/assign-stock', [AssignStockController::class, 'store']);
});

// Driver viewing their own stock and stats
Route::middleware(['auth:sanctum', 'driver'])->group(function () {
    Route::get('/driver/my-stock', [DriverStockController::class, 'myStock']);
    // More specific routes must come before parameterized routes
    Route::get('/driver/my-stock/statistics', [DriverStockController::class, 'stockStatistics']);
    Route::get('/driver/my-stock/{productId}', [DriverStockController::class, 'stockDetail']);
    Route::get('/driver/stats', [DriverStatsController::class, 'index']);
    Route::get('/driver/dashboard', [DriverDashboardController::class, 'index']);
    
    // Products (read-only for drivers - to request stock)
    Route::get('/driver/products', [ProductController::class, 'driverIndex']);
    
    // Stock Orders (drivers can create and view their own)
    Route::post('/stock-orders', [StockOrderController::class, 'store']);
    Route::get('/stock-orders', [StockOrderController::class, 'index']); // Driver's own orders
});

// ============================================
// SALES ROUTES
// ============================================
// Driver can create sales and view their own
Route::middleware(['auth:sanctum', 'driver'])->group(function () {
    Route::post('/sales', [SaleController::class, 'store']);
    Route::get('/sales', [SaleController::class, 'index']); // Driver's own sales
    Route::get('/driver/sales/statistics', [SaleController::class, 'salesStatistics']);
});

// Both admin and driver can view sale details and invoice
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/sales/{id}', [SaleController::class, 'show']);
    Route::get('/sales/{id}/invoice', [SaleController::class, 'invoice']);
});

// Handle OPTIONS request for CORS preflight
Route::options('/sales/{id}/invoice', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
});

// ============================================
// ADMIN REPORTS ROUTES (Admin only)
// ============================================
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/admin/stats', [AdminStatsController::class, 'index']);
    Route::get('/admin/drivers', [DriverController::class, 'index']);
    Route::post('/admin/drivers', [DriverController::class, 'store']);
    Route::get('/admin/drivers/{id}', [DriverController::class, 'show']);
    Route::get('/admin/drivers/{id}/analytics', [DriverController::class, 'analytics']);
    Route::get('/admin/drivers/{id}/stock-history', [DriverController::class, 'stockHistory']);
    Route::get('/admin/drivers/{id}/inventory', [DriverController::class, 'inventory']);
    Route::get('/admin/drivers/{id}/settlement', [DriverController::class, 'settlement']);
    Route::post('/admin/drivers/{id}/perform-inventory', [DriverController::class, 'performInventory']);
    Route::get('/admin/drivers/{id}/inventory-history', [DriverController::class, 'inventoryHistory']);
    Route::put('/admin/drivers/{id}', [DriverController::class, 'update']);
    Route::delete('/admin/drivers/{id}', [DriverController::class, 'destroy']);
    Route::get('/admin/sales', [AdminSalesController::class, 'index']);
    
    // Stock Orders Management (admin)
    Route::get('/admin/stock-orders', [StockOrderController::class, 'index']); // All orders
    Route::post('/admin/stock-orders/{id}/approve', [StockOrderController::class, 'approve']);
    Route::post('/admin/stock-orders/{id}/reject', [StockOrderController::class, 'reject']);

    // ============================================
    // ROOM SIMULATION ROUTES (Admin only)
    // ============================================
    
    // Rooms CRUD
    Route::apiResource('rooms', RoomController::class);
    Route::get('rooms/{id}/stats', [RoomController::class, 'stats']);
    Route::get('rooms/{id}/door', [RoomController::class, 'getDoor']);
    Route::put('rooms/{id}/door', [RoomController::class, 'updateDoor']);
    
    // Layout Generation
    Route::post('rooms/{id}/validate-layout', [RoomLayoutController::class, 'validateBeforeGeneration']);
    Route::post('rooms/{id}/generate-layout', [RoomLayoutController::class, 'generate']);
    Route::get('rooms/{id}/layout', [RoomLayoutController::class, 'show']);
    Route::put('rooms/{id}/layout', [RoomLayoutController::class, 'update']);
    Route::post('rooms/{id}/layout/optimize', [RoomLayoutController::class, 'optimize']);
    Route::post('rooms/{id}/layout/refresh', [RoomLayoutController::class, 'refresh']);
    Route::delete('rooms/{id}/layout', [RoomLayoutController::class, 'destroy']);
    
    // Product Dimensions
    Route::get('products/{id}/dimensions', [ProductDimensionController::class, 'show']);
    Route::post('products/{id}/dimensions', [ProductDimensionController::class, 'store']);
    Route::put('products/{id}/dimensions', [ProductDimensionController::class, 'update']);
    Route::delete('products/{id}/dimensions', [ProductDimensionController::class, 'destroy']);
    Route::get('products/dimensions', [ProductDimensionController::class, 'index']);
    
    // Visualization
    Route::get('rooms/{id}/visualization', [RoomVisualizationController::class, 'index']);
    Route::get('rooms/{id}/visualization/grid', [RoomVisualizationController::class, 'grid']);
    Route::get('rooms/{id}/visualization/3d', [RoomVisualizationController::class, 'threeD']);
    
    // Placements
    Route::get('rooms/{id}/placements', [RoomLayoutController::class, 'placements']);
    Route::post('rooms/{id}/placements', [RoomLayoutController::class, 'addPlacement']);
    Route::put('placements/{id}', [RoomLayoutController::class, 'updatePlacement']);
    Route::delete('placements/{id}', [RoomLayoutController::class, 'deletePlacement']);
});

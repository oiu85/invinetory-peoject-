<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use App\Models\Sale;
use App\Models\WarehouseStock;

class AdminStatsController extends Controller
{
    public function index()
    {
        $totalProducts = Product::count();
        $totalDrivers = User::where('type', 'driver')->count();
        $totalSales = Sale::count();
        $totalRevenue = Sale::sum('total_amount');
        
        // Low stock products (quantity < 10)
        $lowStockProducts = WarehouseStock::where('quantity', '<', 10)
            ->with('product')
            ->get()
            ->map(function ($stock) {
                return [
                    'product_id' => $stock->product_id,
                    'product_name' => $stock->product->name,
                    'quantity' => $stock->quantity,
                ];
            });

        return response()->json([
            'total_products' => $totalProducts,
            'total_drivers' => $totalDrivers,
            'total_sales' => $totalSales,
            'total_revenue' => (float) $totalRevenue,
            'low_stock_products' => $lowStockProducts,
        ]);
    }
}

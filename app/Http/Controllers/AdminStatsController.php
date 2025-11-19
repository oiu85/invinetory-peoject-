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

        // Sales by day (last 7 days)
        $salesByDay = Sale::selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(total_amount) as revenue')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function($item) {
                return [
                    'date' => $item->date,
                    'count' => $item->count,
                    'revenue' => (float) $item->revenue,
                ];
            });

        // Top drivers by sales
        $topDrivers = User::where('type', 'driver')
            ->withCount('sales')
            ->withSum('sales', 'total_amount')
            ->orderBy('sales_count', 'desc')
            ->limit(5)
            ->get()
            ->map(function($driver) {
                return [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'total_sales' => $driver->sales_count,
                    'total_revenue' => (float) ($driver->sales_sum_total_amount ?? 0),
                ];
            });

        // Today's stats
        $todaySales = Sale::whereDate('created_at', today())->count();
        $todayRevenue = Sale::whereDate('created_at', today())->sum('total_amount');

        return response()->json([
            'total_products' => $totalProducts,
            'total_drivers' => $totalDrivers,
            'total_sales' => $totalSales,
            'total_revenue' => (float) $totalRevenue,
            'today_sales' => $todaySales,
            'today_revenue' => (float) $todayRevenue,
            'low_stock_products' => $lowStockProducts,
            'sales_by_day' => $salesByDay,
            'top_drivers' => $topDrivers,
        ]);
    }
}

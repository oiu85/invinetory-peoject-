<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\DriverStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverDashboardController extends Controller
{
    public function index(Request $request)
    {
        $driver = $request->user();
        $lowStockThreshold = 10;

        // Quick stats cards
        // Total sales today
        $todaySales = Sale::where('driver_id', $driver->id)
            ->whereDate('created_at', today())
            ->count();

        // Revenue today
        $todayRevenue = Sale::where('driver_id', $driver->id)
            ->whereDate('created_at', today())
            ->sum('total_amount') ?? 0;

        // Available products count
        $availableProducts = DriverStock::where('driver_id', $driver->id)
            ->where('quantity', '>', 0)
            ->distinct('product_id')
            ->count('product_id');

        // Low stock alerts count
        $lowStockAlerts = DriverStock::where('driver_id', $driver->id)
            ->where('quantity', '<', $lowStockThreshold)
            ->where('quantity', '>', 0)
            ->count();

        // Recent sales (last 5)
        $recentSales = Sale::where('driver_id', $driver->id)
            ->with(['items.product'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'customer_name' => $sale->customer_name,
                    'total_amount' => (float) $sale->total_amount,
                    'items_count' => $sale->items->count(),
                    'created_at' => $sale->created_at->toIso8601String(),
                    'formatted_date' => $sale->created_at->format('Y-m-d H:i:s'),
                ];
            });

        // Low stock products (top 5)
        $lowStockProducts = DriverStock::where('driver_id', $driver->id)
            ->where('quantity', '<', $lowStockThreshold)
            ->where('quantity', '>', 0)
            ->with('product.category')
            ->orderBy('quantity', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($stock) {
                $product = $stock->product;
                return [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_image' => $product->image,
                    'category' => $product->category ? $product->category->name : null,
                    'quantity' => (int) $stock->quantity,
                    'price' => (float) $product->price,
                    'stock_value' => (float) ($stock->quantity * $product->price),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'quick_stats' => [
                    'today_sales' => (int) $todaySales,
                    'today_revenue' => (float) $todayRevenue,
                    'available_products' => (int) $availableProducts,
                    'low_stock_alerts' => (int) $lowStockAlerts,
                ],
                'recent_sales' => $recentSales,
                'low_stock_products' => $lowStockProducts,
            ],
        ]);
    }
}

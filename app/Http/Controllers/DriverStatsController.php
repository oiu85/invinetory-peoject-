<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\DriverStock;
use Illuminate\Http\Request;

class DriverStatsController extends Controller
{
    public function index(Request $request)
    {
        $driver = $request->user();

        // Total sales count
        $totalSales = (int) Sale::where('driver_id', $driver->id)->count();

        // Total revenue
        $totalRevenue = (float) (Sale::where('driver_id', $driver->id)->sum('total_amount') ?? 0);

        // Today's sales count
        $todaySales = (int) Sale::where('driver_id', $driver->id)
            ->whereDate('created_at', today())
            ->count();

        // Today's revenue
        $todayRevenue = (float) (Sale::where('driver_id', $driver->id)
            ->whereDate('created_at', today())
            ->sum('total_amount') ?? 0);

        // Total stock items count
        $totalStockItems = (int) (DriverStock::where('driver_id', $driver->id)
            ->sum('quantity') ?? 0);

        // Recent sales (last 10)
        $recentSales = Sale::where('driver_id', $driver->id)
            ->with(['items.product'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'customer_name' => $sale->customer_name,
                    'total_amount' => (float) $sale->total_amount,
                    'created_at' => $sale->created_at->toIso8601String(),
                    'items' => $sale->items->map(function ($item) {
                        return [
                            'product_name' => $item->product->name,
                            'quantity' => (int) $item->quantity,
                            'price' => (float) $item->price,
                            'subtotal' => (float) ($item->quantity * $item->price),
                        ];
                    }),
                ];
            });

        return response()->json([
            'total_sales' => (int) $totalSales,
            'total_revenue' => (float) $totalRevenue,
            'today_sales' => (int) $todaySales,
            'today_revenue' => (float) $todayRevenue,
            'total_stock_items' => (int) $totalStockItems,
            'recent_sales' => $recentSales,
        ]);
    }
}

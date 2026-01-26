<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\StockAssignment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\InventoryHistory;
use App\Models\DriverStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Omaralalwi\Gpdf\Gpdf;
use Omaralalwi\Gpdf\GpdfConfig;
use Omaralalwi\Gpdf\Enums\GpdfSettingKeys as GpdfSet;

class DriverController extends Controller
{
    public function index()
    {
        $drivers = User::where('type', 'driver')
            ->withCount(['sales', 'driverStock'])
            ->with(['sales' => function($query) {
                $query->selectRaw('driver_id, COUNT(*) as total_sales, SUM(total_amount) as total_revenue')
                    ->groupBy('driver_id');
            }])
            ->get()
            ->map(function($driver) {
                $totalSales = $driver->sales()->count();
                $totalRevenue = $driver->sales()->sum('total_amount');
                $totalStockItems = $driver->driverStock()->sum('quantity');
                
                return [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'email' => $driver->email,
                    'created_at' => $driver->created_at,
                    'total_sales' => $totalSales,
                    'total_revenue' => (float) $totalRevenue,
                    'total_stock_items' => $totalStockItems,
                ];
            });
        
        return response()->json($drivers);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $driver = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'type' => 'driver',
        ]);

        return response()->json([
            'message' => 'Driver created successfully',
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'email' => $driver->email,
                'type' => $driver->type,
            ],
        ], 201);
    }

    public function show(string $id)
    {
        $driver = User::where('type', 'driver')->findOrFail($id);
        
        // Get driver statistics
        $totalSales = $driver->sales()->count();
        $totalRevenue = $driver->sales()->sum('total_amount');
        $totalStockItems = $driver->driverStock()->sum('quantity');
        
        return response()->json([
            'id' => $driver->id,
            'name' => $driver->name,
            'email' => $driver->email,
            'created_at' => $driver->created_at,
            'stats' => [
                'total_sales' => $totalSales,
                'total_revenue' => (float) $totalRevenue,
                'total_stock_items' => $totalStockItems,
            ],
        ]);
    }

    public function analytics(string $id)
    {
        $driver = User::where('type', 'driver')->findOrFail($id);
        
        // Basic stats
        $totalSales = $driver->sales()->count();
        $totalRevenue = $driver->sales()->sum('total_amount');
        $totalStockItems = $driver->driverStock()->sum('quantity');
        
        // Today's stats
        $todaySales = $driver->sales()->whereDate('created_at', today())->count();
        $todayRevenue = $driver->sales()->whereDate('created_at', today())->sum('total_amount');
        
        // This week's stats
        $weekStart = now()->startOfWeek();
        $weekSales = $driver->sales()->where('created_at', '>=', $weekStart)->count();
        $weekRevenue = $driver->sales()->where('created_at', '>=', $weekStart)->sum('total_amount');
        
        // This month's stats
        $monthStart = now()->startOfMonth();
        $monthSales = $driver->sales()->where('created_at', '>=', $monthStart)->count();
        $monthRevenue = $driver->sales()->where('created_at', '>=', $monthStart)->sum('total_amount');
        
        // Average sale amount
        $avgSaleAmount = $totalSales > 0 ? $totalRevenue / $totalSales : 0;
        
        // Sales by day (last 30 days)
        $salesByDay = $driver->sales()
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(total_amount) as revenue')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->map(function($item) {
                return [
                    'date' => $item->date,
                    'count' => (int) $item->count,
                    'revenue' => (float) $item->revenue,
                ];
            });
        
        // Sales by month (last 12 months)
        $salesByMonth = $driver->sales()
            ->where('created_at', '>=', now()->subMonths(12))
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count, SUM(total_amount) as revenue')
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get()
            ->map(function($item) {
                return [
                    'month' => $item->month,
                    'count' => (int) $item->count,
                    'revenue' => (float) $item->revenue,
                ];
            });
        
        // Top selling products
        $topProducts = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.driver_id', $driver->id)
            ->selectRaw('products.id, products.name, SUM(sale_items.quantity) as total_quantity, SUM(sale_items.quantity * sale_items.price) as total_revenue')
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_quantity', 'desc')
            ->limit(10)
            ->get()
            ->map(function($item) {
                return [
                    'product_id' => $item->id,
                    'product_name' => $item->name,
                    'total_quantity' => (int) $item->total_quantity,
                    'total_revenue' => (float) $item->total_revenue,
                ];
            });
        
        // Recent sales (last 20)
        $recentSales = $driver->sales()
            ->with(['items.product'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function($sale) {
                return [
                    'id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'customer_name' => $sale->customer_name,
                    'total_amount' => (float) $sale->total_amount,
                    'created_at' => $sale->created_at,
                    'items_count' => $sale->items->count(),
                    'items' => $sale->items->map(function($item) {
                        $effectivePrice = $item->custom_price ?? $item->price;
                        return [
                            'product_name' => $item->product->name ?? 'N/A',
                            'quantity' => $item->quantity,
                            'price' => (float) $item->price,
                            'original_price' => (float) $item->price,
                            'custom_price' => $item->custom_price ? (float) $item->custom_price : null,
                            'subtotal' => (float) ($item->quantity * $effectivePrice),
                        ];
                    }),
                ];
            });
        
        // Current stock with product details and assignment history
        $currentStock = $driver->driverStock()
            ->with(['product.category'])
            ->get()
            ->map(function($stock) use ($driver) {
                // Get assignment history for this product
                $assignments = StockAssignment::where('driver_id', $driver->id)
                    ->where('product_id', $stock->product_id)
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function($assignment) {
                        return [
                            'id' => $assignment->id,
                            'quantity' => $assignment->quantity,
                            'assigned_from' => $assignment->assigned_from,
                            'cost_price' => (float) ($assignment->product_price_at_assignment ?? 0),
                            'created_at' => $assignment->created_at,
                        ];
                    });
                
                // Calculate average cost price
                $totalCost = $assignments->sum(function($a) {
                    return $a['quantity'] * $a['cost_price'];
                });
                $totalQty = $assignments->sum('quantity');
                $avgCostPrice = $totalQty > 0 ? $totalCost / $totalQty : 0;
                
                return [
                    'id' => $stock->id,
                    'product_id' => $stock->product_id,
                    'product_name' => $stock->product->name ?? 'N/A',
                    'category_name' => $stock->product->category->name ?? 'N/A',
                    'quantity' => $stock->quantity,
                    'product_price' => (float) ($stock->product->price ?? 0),
                    'avg_cost_price' => (float) $avgCostPrice,
                    'total_value' => (float) ($stock->quantity * ($stock->product->price ?? 0)),
                    'total_cost_value' => (float) ($stock->quantity * $avgCostPrice),
                    'assignments_count' => $assignments->count(),
                    'assignments' => $assignments,
                ];
            });
        
        // Performance trends
        $lastMonthSales = $driver->sales()
            ->whereBetween('created_at', [now()->subMonths(2)->startOfMonth(), now()->subMonth()->endOfMonth()])
            ->count();
        $lastMonthRevenue = $driver->sales()
            ->whereBetween('created_at', [now()->subMonths(2)->startOfMonth(), now()->subMonth()->endOfMonth()])
            ->sum('total_amount');
        
        $salesGrowth = $lastMonthSales > 0 ? (($monthSales - $lastMonthSales) / $lastMonthSales) * 100 : 0;
        $revenueGrowth = $lastMonthRevenue > 0 ? (($monthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 : 0;
        
        return response()->json([
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'email' => $driver->email,
                'created_at' => $driver->created_at,
            ],
            'overview' => [
                'total_sales' => $totalSales,
                'total_revenue' => (float) $totalRevenue,
                'total_stock_items' => $totalStockItems,
                'avg_sale_amount' => (float) $avgSaleAmount,
            ],
            'period_stats' => [
                'today' => [
                    'sales' => $todaySales,
                    'revenue' => (float) $todayRevenue,
                ],
                'this_week' => [
                    'sales' => $weekSales,
                    'revenue' => (float) $weekRevenue,
                ],
                'this_month' => [
                    'sales' => $monthSales,
                    'revenue' => (float) $monthRevenue,
                ],
            ],
            'trends' => [
                'sales_growth' => (float) $salesGrowth,
                'revenue_growth' => (float) $revenueGrowth,
            ],
            'charts' => [
                'sales_by_day' => $salesByDay,
                'sales_by_month' => $salesByMonth,
            ],
            'top_products' => $topProducts,
            'recent_sales' => $recentSales,
            'current_stock' => $currentStock,
        ]);
    }

    public function stockHistory(string $id)
    {
        $driver = User::where('type', 'driver')->findOrFail($id);
        
        // Get all stock assignments with product details
        $assignments = StockAssignment::where('driver_id', $driver->id)
            ->with(['product.category', 'room'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($assignment) {
                return [
                    'id' => $assignment->id,
                    'product_id' => $assignment->product_id,
                    'product_name' => $assignment->product->name ?? 'N/A',
                    'category_name' => $assignment->product->category->name ?? 'N/A',
                    'quantity' => $assignment->quantity,
                    'assigned_from' => $assignment->assigned_from,
                    'room_name' => $assignment->room->name ?? 'N/A',
                    'product_price_at_assignment' => (float) ($assignment->product_price_at_assignment ?? 0),
                    'total_cost' => (float) ($assignment->quantity * ($assignment->product_price_at_assignment ?? 0)),
                    'created_at' => $assignment->created_at,
                ];
            });
        
        // Group by product to show total times taken
        $productSummary = StockAssignment::where('driver_id', $driver->id)
            ->selectRaw('product_id, COUNT(*) as times_taken, SUM(quantity) as total_quantity, SUM(quantity * product_price_at_assignment) as total_cost')
            ->groupBy('product_id')
            ->get()
            ->map(function($item) {
                $product = Product::with('category')->find($item->product_id);
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $product->name ?? 'N/A',
                    'category_name' => $product->category->name ?? 'N/A',
                    'times_taken' => (int) $item->times_taken,
                    'total_quantity' => (int) $item->total_quantity,
                    'total_cost' => (float) ($item->total_cost ?? 0),
                    'current_price' => (float) ($product->price ?? 0),
                ];
            });
        
        return response()->json([
            'assignments' => $assignments,
            'product_summary' => $productSummary,
        ]);
    }

    public function inventory(Request $request, string $id)
    {
        $driver = User::where('type', 'driver')->findOrFail($id);
        
        $startDate = $request->input('start_date', now()->startOfWeek()->toDateString());
        $endDate = $request->input('end_date', now()->endOfWeek()->toDateString());
        
        // Get all sales in period
        $sales = Sale::where('driver_id', $driver->id)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->with(['items.product'])
            ->get();
        
        // Calculate profits for each sale item
        $profitDetails = [];
        $totalProfit = 0;
        $totalRevenue = 0;
        $totalCost = 0;
        
        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                // Find the assignment price for this product (FIFO - first assigned)
                $assignment = StockAssignment::where('driver_id', $driver->id)
                    ->where('product_id', $item->product_id)
                    ->where('created_at', '<=', $sale->created_at)
                    ->orderBy('created_at', 'asc')
                    ->first();
                
                $costPrice = $assignment ? (float) $assignment->product_price_at_assignment : (float) $item->product->price;
                $sellingPrice = $item->custom_price ?? (float) $item->price;
                $profit = ($sellingPrice - $costPrice) * $item->quantity;
                
                $profitDetails[] = [
                    'sale_id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'customer_name' => $sale->customer_name,
                    'sale_date' => $sale->created_at,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name ?? 'N/A',
                    'quantity' => $item->quantity,
                    'cost_price' => $costPrice,
                    'selling_price' => $sellingPrice,
                    'profit_per_unit' => $sellingPrice - $costPrice,
                    'total_profit' => $profit,
                    'revenue' => $sellingPrice * $item->quantity,
                    'cost' => $costPrice * $item->quantity,
                ];
                
                $totalProfit += $profit;
                $totalRevenue += $sellingPrice * $item->quantity;
                $totalCost += $costPrice * $item->quantity;
            }
        }
        
        // Current stock value
        $currentStockValue = $driver->driverStock()
            ->with('product')
            ->get()
            ->sum(function($stock) {
                $assignment = StockAssignment::where('driver_id', $stock->driver_id)
                    ->where('product_id', $stock->product_id)
                    ->orderBy('created_at', 'asc')
                    ->first();
                $costPrice = $assignment ? (float) $assignment->product_price_at_assignment : (float) ($stock->product->price ?? 0);
                return $stock->quantity * $costPrice;
            });
        
        return response()->json([
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'email' => $driver->email,
            ],
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'summary' => [
                'total_sales' => $sales->count(),
                'total_revenue' => (float) $totalRevenue,
                'total_cost' => (float) $totalCost,
                'total_profit' => (float) $totalProfit,
                'current_stock_value' => (float) $currentStockValue,
            ],
            'profit_details' => $profitDetails,
            'sales' => $sales->map(function($sale) {
                return [
                    'id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'customer_name' => $sale->customer_name,
                    'total_amount' => (float) $sale->total_amount,
                    'created_at' => $sale->created_at,
                    'items' => $sale->items->map(function($item) {
                        return [
                            'product_name' => $item->product->name ?? 'N/A',
                            'quantity' => $item->quantity,
                            'price' => (float) $item->price,
                        ];
                    }),
                ];
            }),
        ]);
    }

    public function settlement(Request $request, string $id)
    {
        $driver = User::where('type', 'driver')->findOrFail($id);
        
        $startDate = $request->input('start_date', now()->startOfWeek()->toDateString());
        $endDate = $request->input('end_date', now()->endOfWeek()->toDateString());
        $periodType = $request->input('period_type', 'week'); // 'week', 'month', 'custom'
        
        // Calculate inventory data inline (avoid recursion)
        $sales = Sale::where('driver_id', $driver->id)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->with(['items.product'])
            ->get();
        
        $profitDetails = [];
        $totalProfit = 0;
        $totalRevenue = 0;
        $totalCost = 0;
        
        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $assignment = StockAssignment::where('driver_id', $driver->id)
                    ->where('product_id', $item->product_id)
                    ->where('created_at', '<=', $sale->created_at)
                    ->orderBy('created_at', 'asc')
                    ->first();
                
                $costPrice = $assignment ? (float) $assignment->product_price_at_assignment : (float) $item->product->price;
                $sellingPrice = $item->custom_price ?? (float) $item->price;
                $profit = ($sellingPrice - $costPrice) * $item->quantity;
                
                $profitDetails[] = [
                    'sale_id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'customer_name' => $sale->customer_name,
                    'sale_date' => $sale->created_at,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name ?? 'N/A',
                    'quantity' => $item->quantity,
                    'cost_price' => $costPrice,
                    'selling_price' => $sellingPrice,
                    'profit_per_unit' => $sellingPrice - $costPrice,
                    'total_profit' => $profit,
                    'revenue' => $sellingPrice * $item->quantity,
                    'cost' => $costPrice * $item->quantity,
                ];
                
                $totalProfit += $profit;
                $totalRevenue += $sellingPrice * $item->quantity;
                $totalCost += $costPrice * $item->quantity;
            }
        }
        
        $currentStockValue = $driver->driverStock()
            ->with('product')
            ->get()
            ->sum(function($stock) {
                $assignment = StockAssignment::where('driver_id', $stock->driver_id)
                    ->where('product_id', $stock->product_id)
                    ->orderBy('created_at', 'asc')
                    ->first();
                $costPrice = $assignment ? (float) $assignment->product_price_at_assignment : (float) ($stock->product->price ?? 0);
                return $stock->quantity * $costPrice;
            });
        
        $inventory = [
            'summary' => [
                'total_sales' => $sales->count(),
                'total_revenue' => (float) $totalRevenue,
                'total_cost' => (float) $totalCost,
                'total_profit' => (float) $totalProfit,
                'current_stock_value' => (float) $currentStockValue,
            ],
            'profit_details' => $profitDetails,
            'sales' => $sales->map(function($sale) {
                return [
                    'id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'customer_name' => $sale->customer_name,
                    'total_amount' => (float) $sale->total_amount,
                    'created_at' => $sale->created_at,
                    'items' => $sale->items->map(function($item) {
                        return [
                            'product_name' => $item->product->name ?? 'N/A',
                            'quantity' => $item->quantity,
                            'price' => (float) $item->price,
                        ];
                    }),
                ];
            }),
        ];
        
        // Generate settlement invoice HTML
        $html = view('driver_settlement', [
            'driver' => $driver,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'type' => $periodType,
            ],
            'summary' => $inventory['summary'],
            'profit_details' => $inventory['profit_details'],
            'sales' => $inventory['sales'],
        ])->render();
        
        // Ensure UTF-8 encoding
        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        
        // Use Gpdf for proper Arabic text support
        try {
            $config = new GpdfConfig([
                'defaultPaperSize' => 'a4',
                'defaultPaperOrientation' => 'portrait',
            ]);
            $gpdf = new Gpdf($config);
            $pdfContent = $gpdf->generate($html);
        } catch (\Exception $e) {
            Log::error('Gpdf error: ' . $e->getMessage());
            Log::error('Gpdf stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error generating PDF: ' . $e->getMessage(),
            ], 500);
        }
        
        $filename = 'settlement-' . $driver->name . '-' . $startDate . '-to-' . $endDate . '.pdf';
        
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
    }

    public function update(Request $request, string $id)
    {
        $driver = User::where('type', 'driver')->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
            'password' => 'sometimes|string|min:6',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $driver->update($validated);

        return response()->json([
            'message' => 'Driver updated successfully',
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'email' => $driver->email,
                'type' => $driver->type,
            ],
        ]);
    }

    public function destroy(string $id)
    {
        $driver = User::where('type', 'driver')->findOrFail($id);
        $driver->delete();

        return response()->json(['message' => 'Driver deleted successfully']);
    }

    /**
     * Perform inventory for a driver
     * Calculates earnings, creates snapshot, and resets earnings to 0
     */
    public function performInventory(Request $request, string $id)
    {
        $driver = User::where('type', 'driver')->findOrFail($id);

        $validated = $request->validate([
            'period_start_date' => 'required|date',
            'period_end_date' => 'required|date|after_or_equal:period_start_date',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Get last inventory date (if exists) or driver creation date
        $lastInventory = InventoryHistory::where('driver_id', $driver->id)
            ->orderBy('performed_at', 'desc')
            ->first();
        
        $periodStart = $lastInventory 
            ? $lastInventory->performed_at 
            : $driver->created_at;

        // Get current stock snapshot
        $stockSnapshot = $driver->driverStock()
            ->with(['product.category'])
            ->get()
            ->map(function($stock) use ($driver) {
                $assignment = StockAssignment::where('driver_id', $driver->id)
                    ->where('product_id', $stock->product_id)
                    ->orderBy('created_at', 'asc')
                    ->first();
                $costPrice = $assignment 
                    ? (float) $assignment->product_price_at_assignment 
                    : (float) ($stock->product->price ?? 0);
                
                return [
                    'product_id' => $stock->product_id,
                    'product_name' => $stock->product->name ?? 'N/A',
                    'category_name' => $stock->product->category->name ?? 'N/A',
                    'quantity' => $stock->quantity,
                    'product_price' => (float) ($stock->product->price ?? 0),
                    'cost_price' => $costPrice,
                    'total_value' => (float) ($stock->quantity * ($stock->product->price ?? 0)),
                    'total_cost_value' => (float) ($stock->quantity * $costPrice),
                ];
            });

        // Calculate total stock value and cost value
        $totalStockValue = $stockSnapshot->sum('total_value');
        $totalCostValue = $stockSnapshot->sum('total_cost_value');

        // Calculate earnings from sales since last inventory
        $sales = Sale::where('driver_id', $driver->id)
            ->where('created_at', '>=', $periodStart)
            ->where('created_at', '<=', $validated['period_end_date'] . ' 23:59:59')
            ->with(['items.product'])
            ->get();

        $earningsBeforeReset = 0;
        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                // Use custom_price if available, otherwise use price
                $sellingPrice = $item->custom_price ?? $item->price;
                
                // Find cost price from assignment (FIFO)
                $assignment = StockAssignment::where('driver_id', $driver->id)
                    ->where('product_id', $item->product_id)
                    ->where('created_at', '<=', $sale->created_at)
                    ->orderBy('created_at', 'asc')
                    ->first();
                
                $costPrice = $assignment 
                    ? (float) $assignment->product_price_at_assignment 
                    : (float) $item->product->price;
                
                $profit = ((float) $sellingPrice - $costPrice) * $item->quantity;
                $earningsBeforeReset += $profit;
            }
        }

        // Create inventory history record
        $inventoryHistory = InventoryHistory::create([
            'driver_id' => $driver->id,
            'performed_at' => now(),
            'stock_snapshot' => $stockSnapshot->toArray(),
            'earnings_before_reset' => $earningsBeforeReset,
            'earnings_after_reset' => 0,
            'total_stock_value' => $totalStockValue,
            'total_cost_value' => $totalCostValue,
            'period_start_date' => $validated['period_start_date'],
            'period_end_date' => $validated['period_end_date'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inventory performed successfully',
            'data' => [
                'id' => $inventoryHistory->id,
                'driver_id' => $inventoryHistory->driver_id,
                'performed_at' => $inventoryHistory->performed_at->toIso8601String(),
                'earnings_before_reset' => (float) $inventoryHistory->earnings_before_reset,
                'earnings_after_reset' => (float) $inventoryHistory->earnings_after_reset,
                'total_stock_value' => (float) $inventoryHistory->total_stock_value,
                'total_cost_value' => (float) $inventoryHistory->total_cost_value,
                'period_start_date' => $inventoryHistory->period_start_date->toDateString(),
                'period_end_date' => $inventoryHistory->period_end_date->toDateString(),
                'notes' => $inventoryHistory->notes,
                'stock_snapshot_count' => count($stockSnapshot),
            ],
        ], 201);
    }

    /**
     * Get inventory history for a driver
     */
    public function inventoryHistory(Request $request, string $id)
    {
        $driver = User::where('type', 'driver')->findOrFail($id);

        $perPage = $request->input('per_page', 15);
        $inventoryHistory = InventoryHistory::where('driver_id', $driver->id)
            ->orderBy('performed_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $inventoryHistory->map(function($history) {
                return [
                    'id' => $history->id,
                    'performed_at' => $history->performed_at->toIso8601String(),
                    'earnings_before_reset' => (float) $history->earnings_before_reset,
                    'earnings_after_reset' => (float) $history->earnings_after_reset,
                    'total_stock_value' => (float) $history->total_stock_value,
                    'total_cost_value' => (float) $history->total_cost_value,
                    'period_start_date' => $history->period_start_date->toDateString(),
                    'period_end_date' => $history->period_end_date->toDateString(),
                    'notes' => $history->notes,
                    'stock_snapshot' => $history->stock_snapshot,
                ];
            }),
            'meta' => [
                'current_page' => $inventoryHistory->currentPage(),
                'per_page' => $inventoryHistory->perPage(),
                'total' => $inventoryHistory->total(),
                'last_page' => $inventoryHistory->lastPage(),
            ],
        ]);
    }
}


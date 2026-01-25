<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\DriverStock;
use App\Http\Requests\Driver\SalesListRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Omaralalwi\Gpdf\Gpdf;
use Omaralalwi\Gpdf\GpdfConfig;
use Omaralalwi\Gpdf\Enums\GpdfSettingKeys as GpdfSet;

class SaleController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $driver = $request->user();
        $totalAmount = 0;
        $saleItems = [];

        // Validate driver stock and calculate total
        foreach ($validated['items'] as $item) {
            $driverStock = DriverStock::where('driver_id', $driver->id)
                ->where('product_id', $item['product_id'])
                ->first();

            if (!$driverStock || $driverStock->quantity < $item['quantity']) {
                return response()->json([
                    'message' => "Insufficient stock for product ID: {$item['product_id']}"
                ], 400);
            }

            $product = $driverStock->product;
            $itemTotal = $product->price * $item['quantity'];
            $totalAmount += $itemTotal;

            $saleItems[] = [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $product->price,
                'total' => $itemTotal,
            ];
        }

        // Create sale
        $invoiceNumber = 'INV-' . strtoupper(Str::random(8)) . '-' . now()->format('Ymd');
        
        $sale = Sale::create([
            'driver_id' => $driver->id,
            'customer_name' => $validated['customer_name'],
            'total_amount' => $totalAmount,
            'invoice_number' => $invoiceNumber,
        ]);

        // Create sale items and decrease driver stock
        foreach ($saleItems as $item) {
            SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
            ]);

            $driverStock = DriverStock::where('driver_id', $driver->id)
                ->where('product_id', $item['product_id'])
                ->first();
            
            $driverStock->decrement('quantity', $item['quantity']);
        }

        $sale->load(['items.product', 'driver']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'customer_name' => $sale->customer_name,
                'total_amount' => (float) $sale->total_amount,
                'items' => $sale->items->map(function ($item) {
                    return [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name,
                        'quantity' => (int) $item->quantity,
                        'price' => (float) $item->price,
                        'subtotal' => (float) ($item->quantity * $item->price),
                    ];
                }),
                'created_at' => $sale->created_at->toIso8601String(),
            ],
        ], 201);
    }

    public function show(Request $request, string $id)
    {
        try {
            $user = $request->user();
            
            // Build query based on user type
            $query = Sale::with(['items.product', 'driver']);
            
            // If user is a driver (not admin), only show their own sales
            if ($user->isDriver()) {
                $query->where('driver_id', $user->id);
            }
            
            // Admin can view any sale, so no additional filter needed
            $sale = $query->find($id);

            // Check if sale exists
            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sale not found.',
                ], 404);
            }

            // Additional security check: ensure driver can only access their own sales
            if ($user->isDriver() && $sale->driver_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this sale.',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'customer_name' => $sale->customer_name,
                    'total_amount' => (float) $sale->total_amount,
                    'driver_name' => $sale->driver ? $sale->driver->name : 'N/A',
                    'driver' => $sale->driver ? [
                        'id' => $sale->driver->id,
                        'name' => $sale->driver->name,
                        'email' => $sale->driver->email,
                    ] : null,
                    'items_count' => $sale->items->count(),
                    'items' => $sale->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'product_id' => $item->product_id,
                            'product_name' => $item->product ? $item->product->name : 'N/A',
                            'product_image' => $item->product ? $item->product->image : null,
                            'quantity' => (int) $item->quantity,
                            'price' => (float) $item->price,
                            'subtotal' => (float) ($item->quantity * $item->price),
                        ];
                    }),
                    'created_at' => $sale->created_at->toIso8601String(),
                    'formatted_date' => $sale->created_at->format('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching sale details: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function index(SalesListRequest $request)
    {
        $driver = $request->user();

        $query = Sale::where('driver_id', $driver->id)
            ->with(['items.product']);

        // Search by customer name or invoice number
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('invoice_number', 'like', "%{$search}%");
            });
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Sorting
        $sortBy = $request->get('sort', 'date'); // default: date
        $sortOrder = $request->get('order', 'desc'); // default: desc

        switch ($sortBy) {
            case 'date':
                $query->orderBy('created_at', $sortOrder);
                break;
            case 'amount':
                $query->orderBy('total_amount', $sortOrder);
                break;
            case 'customer':
                $query->orderBy('customer_name', $sortOrder);
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $sales = $query->paginate($perPage);

        // Format response
        $formattedSales = $sales->map(function ($sale) {
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

        return response()->json([
            'success' => true,
            'data' => $formattedSales,
            'meta' => [
                'current_page' => $sales->currentPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
                'last_page' => $sales->lastPage(),
            ],
        ]);
    }

    public function salesStatistics(Request $request)
    {
        $driver = $request->user();

        // Total sales count (all time)
        $totalSales = Sale::where('driver_id', $driver->id)->count();

        // Total revenue (all time)
        $totalRevenue = Sale::where('driver_id', $driver->id)->sum('total_amount') ?? 0;

        // Today's sales
        $todaySales = Sale::where('driver_id', $driver->id)
            ->whereDate('created_at', today())
            ->count();

        $todayRevenue = Sale::where('driver_id', $driver->id)
            ->whereDate('created_at', today())
            ->sum('total_amount') ?? 0;

        // This week's sales
        $weekStart = now()->startOfWeek();
        $weekSales = Sale::where('driver_id', $driver->id)
            ->where('created_at', '>=', $weekStart)
            ->count();

        $weekRevenue = Sale::where('driver_id', $driver->id)
            ->where('created_at', '>=', $weekStart)
            ->sum('total_amount') ?? 0;

        // This month's sales
        $monthStart = now()->startOfMonth();
        $monthSales = Sale::where('driver_id', $driver->id)
            ->where('created_at', '>=', $monthStart)
            ->count();

        $monthRevenue = Sale::where('driver_id', $driver->id)
            ->where('created_at', '>=', $monthStart)
            ->sum('total_amount') ?? 0;

        // Average sale amount
        $averageSaleAmount = $totalSales > 0 ? ($totalRevenue / $totalSales) : 0;

        // Top selling products (last 30 days)
        $topProducts = SaleItem::whereHas('sale', function ($q) use ($driver) {
            $q->where('driver_id', $driver->id)
              ->where('created_at', '>=', now()->subDays(30));
        })
        ->join('products', 'sale_items.product_id', '=', 'products.id')
        ->select(
            'products.id',
            'products.name',
            'products.image',
            \Illuminate\Support\Facades\DB::raw('SUM(sale_items.quantity) as total_quantity'),
            \Illuminate\Support\Facades\DB::raw('SUM(sale_items.quantity * sale_items.price) as total_revenue')
        )
        ->groupBy('products.id', 'products.name', 'products.image')
        ->orderBy('total_quantity', 'desc')
        ->limit(10)
        ->get()
        ->map(function ($item) {
            return [
                'product_id' => $item->id,
                'product_name' => $item->name,
                'product_image' => $item->image,
                'total_quantity_sold' => (int) $item->total_quantity,
                'total_revenue' => (float) $item->total_revenue,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'all_time' => [
                    'sales_count' => (int) $totalSales,
                    'revenue' => (float) $totalRevenue,
                ],
                'today' => [
                    'sales_count' => (int) $todaySales,
                    'revenue' => (float) $todayRevenue,
                ],
                'this_week' => [
                    'sales_count' => (int) $weekSales,
                    'revenue' => (float) $weekRevenue,
                ],
                'this_month' => [
                    'sales_count' => (int) $monthSales,
                    'revenue' => (float) $monthRevenue,
                ],
                'average_sale_amount' => (float) $averageSaleAmount,
                'top_products' => $topProducts,
            ],
        ]);
    }

    public function invoice(Request $request, string $id)
    {
        $sale = Sale::with(['items.product', 'driver'])->findOrFail($id);
        
        // Check if user has access (admin or the driver who made the sale)
        $user = $request->user();
        if ($user->type !== 'admin' && $sale->driver_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $html = view('invoice', [
            'sale' => $sale,
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
        
        // Return PDF with CORS headers
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="invoice-'.$sale->invoice_number.'.pdf"')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
    }
}

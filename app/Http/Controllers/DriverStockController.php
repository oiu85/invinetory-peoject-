<?php

namespace App\Http\Controllers;

use App\Models\DriverStock;
use App\Models\User;
use App\Models\StockAssignment;
use App\Http\Requests\Driver\StockListRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverStockController extends Controller
{
    public function show(string $id)
    {
        $driver = User::findOrFail($id);
        
        if (!$driver->isDriver()) {
            return response()->json(['message' => 'User is not a driver'], 400);
        }

        $stock = DriverStock::where('driver_id', $id)
            ->with('product.category')
            ->get();

        return response()->json($stock);
    }

    public function myStock(StockListRequest $request)
    {
        $driver = $request->user();

        $query = DriverStock::where('driver_id', $driver->id)
            ->with(['product.category', 'product.productDimension']);

        // Search by product name
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->has('category_id') && $request->category_id) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('category_id', $request->category_id);
            });
        }

        // Filter low stock only
        if ($request->has('low_stock_only') && $request->boolean('low_stock_only')) {
            $threshold = $request->get('low_stock_threshold', 10);
            $query->where('quantity', '<', $threshold);
        }

        // Sorting
        $sortBy = $request->get('sort', 'name'); // default: name
        $sortOrder = $request->get('order', 'asc'); // default: asc

        // For name and price sorting, we need to join products
        if (in_array($sortBy, ['name', 'price'])) {
            $query->leftJoin('products', 'driver_stock.product_id', '=', 'products.id')
                ->select(
                    'driver_stock.id',
                    'driver_stock.driver_id',
                    'driver_stock.product_id',
                    'driver_stock.quantity',
                    'driver_stock.created_at',
                    'driver_stock.updated_at'
                )
                ->distinct();
        }

        switch ($sortBy) {
            case 'name':
                $query->orderBy('products.name', $sortOrder);
                break;
            case 'quantity':
                $query->orderBy('driver_stock.quantity', $sortOrder);
                break;
            case 'price':
                $query->orderBy('products.price', $sortOrder);
                break;
            default:
                $query->orderBy('driver_stock.id', 'asc');
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $stock = $query->paginate($perPage);

        // Format response
        $formattedStock = $stock->map(function ($item) {
            $product = $item->product;
            $stockValue = $item->quantity * $product->price;
            $lowStockThreshold = 10;
            
            // Handle null updated_at - use created_at as fallback, or current time if both are null
            $updatedAt = $item->updated_at 
                ? $item->updated_at->toIso8601String()
                : ($item->created_at 
                    ? $item->created_at->toIso8601String()
                    : now()->toIso8601String());
            
            return [
                'id' => $item->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_description' => $product->description,
                'product_image' => $product->image,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                ] : null,
                'price' => (float) $product->price,
                'quantity' => (int) $item->quantity,
                'stock_value' => (float) $stockValue,
                'is_low_stock' => $item->quantity < $lowStockThreshold,
                'updated_at' => $updatedAt,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedStock,
            'meta' => [
                'current_page' => $stock->currentPage(),
                'per_page' => $stock->perPage(),
                'total' => $stock->total(),
                'last_page' => $stock->lastPage(),
            ],
        ]);
    }

    public function stockDetail(Request $request, int $productId)
    {
        $driver = $request->user();

        $stock = DriverStock::where('driver_id', $driver->id)
            ->where('product_id', $productId)
            ->with(['product.category', 'product.productDimension'])
            ->first();

        if (!$stock) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found in your stock.',
            ], 404);
        }

        $product = $stock->product;
        $stockValue = $stock->quantity * $product->price;
        $lowStockThreshold = 10;

        // Get assignment history
        $assignments = StockAssignment::where('driver_id', $driver->id)
            ->where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($assignment) {
                // Handle null created_at
                $assignedAt = $assignment->created_at 
                    ? $assignment->created_at->toIso8601String()
                    : now()->toIso8601String();
                
                return [
                    'id' => $assignment->id,
                    'quantity' => (int) $assignment->quantity,
                    'price_at_assignment' => $assignment->product_price_at_assignment ? (float) $assignment->product_price_at_assignment : null,
                    'assigned_from' => $assignment->assigned_from,
                    'assigned_at' => $assignedAt,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $stock->id,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'image' => $product->image,
                    'price' => (float) $product->price,
                    'category' => $product->category ? [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                        'description' => $product->category->description,
                    ] : null,
                    'dimensions' => $product->productDimension ? [
                        'width' => (float) $product->productDimension->width,
                        'depth' => (float) $product->productDimension->depth,
                        'height' => (float) $product->productDimension->height,
                        'weight' => (float) $product->productDimension->weight,
                    ] : null,
                ],
                'quantity' => (int) $stock->quantity,
                'stock_value' => (float) $stockValue,
                'is_low_stock' => $stock->quantity < $lowStockThreshold,
                'last_updated' => $stock->updated_at 
                    ? $stock->updated_at->toIso8601String()
                    : ($stock->created_at 
                        ? $stock->created_at->toIso8601String()
                        : now()->toIso8601String()),
                'assignment_history' => $assignments,
            ],
        ]);
    }

    public function stockStatistics(Request $request)
    {
        $driver = $request->user();
        $lowStockThreshold = $request->get('low_stock_threshold', 10);

        // Total products count
        $totalProducts = DriverStock::where('driver_id', $driver->id)
            ->distinct('product_id')
            ->count('product_id');

        // Total items quantity
        $totalQuantity = DriverStock::where('driver_id', $driver->id)
            ->sum('quantity') ?? 0;

        // Low stock alerts
        $lowStockItems = DriverStock::where('driver_id', $driver->id)
            ->where('quantity', '<', $lowStockThreshold)
            ->with('product')
            ->get()
            ->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'quantity' => (int) $item->quantity,
                ];
            });

        // Stock value (sum of quantity × price)
        $stockValue = DriverStock::where('driver_id', $driver->id)
            ->join('products', 'driver_stock.product_id', '=', 'products.id')
            ->selectRaw('SUM(driver_stock.quantity * products.price) as total_value')
            ->value('total_value') ?? 0;

        // Products by category breakdown
        $categoryBreakdown = DriverStock::where('driver_stock.driver_id', $driver->id)
            ->join('products', 'driver_stock.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'categories.id as category_id',
                'categories.name as category_name',
                DB::raw('COUNT(DISTINCT driver_stock.product_id) as product_count'),
                DB::raw('SUM(driver_stock.quantity) as total_quantity'),
                DB::raw('SUM(driver_stock.quantity * products.price) as category_value')
            )
            ->groupBy('categories.id', 'categories.name')
            ->get()
            ->map(function ($item) {
                return [
                    'category_id' => $item->category_id,
                    'category_name' => $item->category_name ?? 'Uncategorized',
                    'product_count' => (int) $item->product_count,
                    'total_quantity' => (int) $item->total_quantity,
                    'category_value' => (float) $item->category_value,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'total_products' => (int) $totalProducts,
                'total_quantity' => (int) $totalQuantity,
                'stock_value' => (float) $stockValue,
                'low_stock_threshold' => (int) $lowStockThreshold,
                'low_stock_count' => $lowStockItems->count(),
                'low_stock_items' => $lowStockItems,
                'category_breakdown' => $categoryBreakdown,
            ],
        ]);
    }
}

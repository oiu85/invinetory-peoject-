<?php

namespace App\Http\Controllers;

use App\Events\StockOrderCreated;
use App\Events\StockOrderStatusChanged;
use App\Models\StockOrder;
use App\Models\Product;
use App\Models\WarehouseStock;
use App\Models\DriverStock;
use App\Models\Room;
use App\Models\RoomStock;
use App\Services\FcmNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockOrderController extends Controller
{
    private FcmNotificationService $fcmService;

    public function __construct(FcmNotificationService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Create a new stock order (driver only).
     * Supports both single product and bulk creation.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $driver = $request->user();

        // Check if request contains products array (bulk) or single product
        if ($request->has('products') && is_array($request->products)) {
            // Bulk creation - multiple products
            return $this->storeBulk($request, $driver);
        } else {
            // Single product creation (backward compatible)
            return $this->storeSingle($request, $driver);
        }
    }

    /**
     * Create a single stock order.
     *
     * @param Request $request
     * @param $driver
     * @return JsonResponse
     */
    private function storeSingle(Request $request, $driver): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        // Verify product exists
        $product = Product::findOrFail($validated['product_id']);

        // Check if there's already a pending order for this product
        $existingOrder = StockOrder::where('driver_id', $driver->id)
            ->where('product_id', $validated['product_id'])
            ->where('status', 'pending')
            ->first();

        if ($existingOrder) {
            // Load product relationship for better error message
            $existingOrder->load('product');
            
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending order for this product',
                'error' => 'DUPLICATE_PENDING_ORDER',
                'data' => [
                    'existing_order' => [
                        'order_id' => $existingOrder->id,
                        'product_id' => $existingOrder->product_id,
                        'product_name' => $existingOrder->product->name ?? 'Unknown',
                        'quantity' => $existingOrder->quantity,
                        'status' => $existingOrder->status,
                        'created_at' => $existingOrder->created_at->toIso8601String(),
                        'created_at_human' => $existingOrder->created_at->diffForHumans(),
                    ],
                ],
            ], 400);
        }

        try {
            $stockOrder = StockOrder::create([
                'driver_id' => $driver->id,
                'product_id' => $validated['product_id'],
                'quantity' => $validated['quantity'],
                'status' => 'pending',
            ]);

            $stockOrder->load(['product', 'driver']);

            // Dispatch StockOrderCreated event
            Log::info('Dispatching StockOrderCreated event', [
                'order_id' => $stockOrder->id,
                'driver_id' => $stockOrder->driver_id,
                'product_id' => $stockOrder->product_id,
            ]);
            event(new StockOrderCreated($stockOrder));

            return response()->json([
                'success' => true,
                'message' => 'Stock order created successfully',
                'data' => [
                    'id' => $stockOrder->id,
                    'product_id' => $stockOrder->product_id,
                    'product_name' => $stockOrder->product->name,
                    'quantity' => $stockOrder->quantity,
                    'status' => $stockOrder->status,
                    'created_at' => $stockOrder->created_at->toIso8601String(),
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create stock order: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create stock order',
            ], 500);
        }
    }

    /**
     * Create multiple stock orders in bulk.
     *
     * @param Request $request
     * @param $driver
     * @return JsonResponse
     */
    private function storeBulk(Request $request, $driver): JsonResponse
    {
        $validated = $request->validate([
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $createdOrders = [];
            $skippedProducts = [];
            $errors = [];

            foreach ($validated['products'] as $index => $productData) {
                $productId = $productData['product_id'];
                $quantity = $productData['quantity'];

                // Check if there's already a pending order for this product
                $existingOrder = StockOrder::where('driver_id', $driver->id)
                    ->where('product_id', $productId)
                    ->where('status', 'pending')
                    ->first();

                if ($existingOrder) {
                    // Load product relationship
                    $existingOrder->load('product');
                    
                    $skippedProducts[] = [
                        'product_id' => $productId,
                        'reason' => 'You already have a pending order for this product',
                        'error' => 'DUPLICATE_PENDING_ORDER',
                        'existing_order' => [
                            'order_id' => $existingOrder->id,
                            'product_id' => $existingOrder->product_id,
                            'product_name' => $existingOrder->product->name ?? 'Unknown',
                            'quantity' => $existingOrder->quantity,
                            'status' => $existingOrder->status,
                            'created_at' => $existingOrder->created_at->toIso8601String(),
                        ],
                    ];
                    continue;
                }

                // Verify product exists
                $product = Product::find($productId);
                if (!$product) {
                    $errors[] = [
                        'product_id' => $productId,
                        'error' => 'Product not found',
                    ];
                    continue;
                }

                $stockOrder = StockOrder::create([
                    'driver_id' => $driver->id,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'status' => 'pending',
                ]);

                $stockOrder->load(['product', 'driver']);
                $createdOrders[] = $stockOrder;

                // Dispatch StockOrderCreated event for each order
                Log::info('Dispatching StockOrderCreated event (bulk)', [
                    'order_id' => $stockOrder->id,
                    'driver_id' => $stockOrder->driver_id,
                    'product_id' => $stockOrder->product_id,
                ]);
                event(new StockOrderCreated($stockOrder));
            }

            DB::commit();

            $response = [
                'success' => true,
                'message' => count($createdOrders) . ' stock order(s) created successfully',
                'data' => array_map(function ($order) {
                    return [
                        'id' => $order->id,
                        'product_id' => $order->product_id,
                        'product_name' => $order->product->name,
                        'quantity' => $order->quantity,
                        'status' => $order->status,
                        'created_at' => $order->created_at->toIso8601String(),
                    ];
                }, $createdOrders),
            ];

            if (!empty($skippedProducts)) {
                $response['skipped'] = $skippedProducts;
            }

            if (!empty($errors)) {
                $response['errors'] = $errors;
            }

            return response()->json($response, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create bulk stock orders: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create stock orders: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List stock orders (filtered by user type).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = StockOrder::with(['product', 'driver', 'approver']);

        // Drivers can only see their own orders
        if ($user->isDriver()) {
            $query->where('driver_id', $user->id);
        }

        // Admins can filter by status
        if ($user->isAdmin() && $request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by product name
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $orders = $query->paginate($perPage);

        // Get all unique product IDs from orders
        $productIds = $orders->pluck('product_id')->unique()->toArray();
        
        // Fetch all warehouse stock records in one query
        $warehouseStocks = WarehouseStock::whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');

        $formattedOrders = $orders->map(function ($order) use ($warehouseStocks) {
            // Get warehouse stock for this product (from pre-loaded collection)
            $warehouseStock = $warehouseStocks->get($order->product_id);
            $availableStock = $warehouseStock?->quantity ?? 0;
            $canApprove = $availableStock >= $order->quantity;
            
            return [
                'id' => $order->id,
                'driver_id' => $order->driver_id,
                'driver_name' => $order->driver->name ?? 'N/A',
                'product_id' => $order->product_id,
                'product_name' => $order->product->name ?? 'N/A',
                'quantity' => $order->quantity,
                'status' => $order->status,
                'approved_by' => $order->approved_by,
                'approver_name' => $order->approver->name ?? null,
                'rejection_reason' => $order->rejection_reason,
                'warehouse_stock_available' => $availableStock,
                'can_approve' => $canApprove,
                'stock_shortage' => $canApprove ? 0 : ($order->quantity - $availableStock),
                'created_at' => $order->created_at->toIso8601String(),
                'updated_at' => $order->updated_at->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedOrders,
            'meta' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }

    /**
     * Approve a stock order (admin only).
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();

        DB::beginTransaction();
        try {
            // Lock the stock order row to prevent race conditions
            $stockOrder = StockOrder::with(['product', 'driver'])
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            // Check if order is still pending (might have been changed by another request)
            if ($stockOrder->status !== 'pending') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Order is not pending. Current status: ' . $stockOrder->status,
                    'current_status' => $stockOrder->status,
                ], 400);
            }

            // Lock and check warehouse stock availability (prevents race conditions)
            $warehouseStock = WarehouseStock::where('product_id', $stockOrder->product_id)
                ->lockForUpdate()
                ->first();

            // Check if warehouse stock record exists
            if (!$warehouseStock) {
                DB::rollBack();
                Log::warning('Warehouse stock record not found for product', [
                    'product_id' => $stockOrder->product_id,
                    'order_id' => $stockOrder->id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Warehouse stock record not found for this product',
                    'product_id' => $stockOrder->product_id,
                    'product_name' => $stockOrder->product->name ?? 'Unknown',
                ], 400);
            }

            $availableWarehouse = $warehouseStock->quantity ?? 0;

            // Check if sufficient stock is available
            if ($availableWarehouse < $stockOrder->quantity) {
                DB::rollBack();
                Log::info('Insufficient warehouse stock for order approval', [
                    'order_id' => $stockOrder->id,
                    'product_id' => $stockOrder->product_id,
                    'requested' => $stockOrder->quantity,
                    'available' => $availableWarehouse,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient warehouse stock',
                    'available_in_warehouse' => $availableWarehouse,
                    'requested' => $stockOrder->quantity,
                    'shortage' => $stockOrder->quantity - $availableWarehouse,
                    'product_name' => $stockOrder->product->name ?? 'Unknown',
                ], 400);
            }

            // Update order status
            $stockOrder->update([
                'status' => 'approved',
                'approved_by' => $admin->id,
            ]);

            // Decrease warehouse stock (atomic operation)
            $warehouseStock->decrement('quantity', $stockOrder->quantity);

            // Increase driver stock
            $driverStock = DriverStock::updateOrCreate(
                [
                    'driver_id' => $stockOrder->driver_id,
                    'product_id' => $stockOrder->product_id,
                ],
                ['quantity' => 0] // Initialize with 0 if creating new record
            );
            $driverStock->increment('quantity', $stockOrder->quantity);

            DB::commit();

            // Refresh relationships
            $stockOrder->refresh();
            $driverStock->refresh();

            // Dispatch StockOrderStatusChanged event
            Log::info('Dispatching StockOrderStatusChanged event (approved)', [
                'order_id' => $stockOrder->id,
                'driver_id' => $stockOrder->driver_id,
                'old_status' => 'pending',
                'new_status' => 'approved',
            ]);
            event(new StockOrderStatusChanged($stockOrder, 'pending', 'approved'));

            Log::info('Stock order approved successfully', [
                'order_id' => $stockOrder->id,
                'driver_id' => $stockOrder->driver_id,
                'product_id' => $stockOrder->product_id,
                'quantity' => $stockOrder->quantity,
                'approved_by' => $admin->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Stock order approved successfully',
                'data' => [
                    'id' => $stockOrder->id,
                    'status' => $stockOrder->status,
                    'driver_stock' => $driverStock->fresh(['product']),
                    'warehouse_stock_remaining' => $warehouseStock->fresh()->quantity,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            Log::error('Stock order not found', [
                'order_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Stock order not found',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve stock order: ' . $e->getMessage(), [
                'order_id' => $id,
                'admin_id' => $admin->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve stock order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject a stock order (admin only).
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            // Lock the stock order row to prevent race conditions
            $stockOrder = StockOrder::with(['product', 'driver'])
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            // Check if order is still pending (might have been changed by another request)
            if ($stockOrder->status !== 'pending') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Order is not pending. Current status: ' . $stockOrder->status,
                    'current_status' => $stockOrder->status,
                ], 400);
            }

            $oldStatus = $stockOrder->status;
            
            $stockOrder->update([
                'status' => 'rejected',
                'approved_by' => $admin->id,
                'rejection_reason' => $validated['rejection_reason'] ?? null,
            ]);

            DB::commit();

            // Refresh relationships
            $stockOrder->refresh();

            // Dispatch StockOrderStatusChanged event
            Log::info('Dispatching StockOrderStatusChanged event (rejected)', [
                'order_id' => $stockOrder->id,
                'driver_id' => $stockOrder->driver_id,
                'old_status' => $oldStatus,
                'new_status' => 'rejected',
            ]);
            event(new StockOrderStatusChanged($stockOrder, $oldStatus, 'rejected'));

            Log::info('Stock order rejected', [
                'order_id' => $stockOrder->id,
                'driver_id' => $stockOrder->driver_id,
                'product_id' => $stockOrder->product_id,
                'quantity' => $stockOrder->quantity,
                'rejected_by' => $admin->id,
                'rejection_reason' => $validated['rejection_reason'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Stock order rejected successfully',
                'data' => [
                    'id' => $stockOrder->id,
                    'status' => $stockOrder->status,
                    'rejection_reason' => $stockOrder->rejection_reason,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            Log::error('Stock order not found for rejection', [
                'order_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Stock order not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reject stock order: ' . $e->getMessage(), [
                'order_id' => $id,
                'admin_id' => $admin->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reject stock order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a pending stock order (driver only).
     * Drivers can only cancel their own pending orders.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $driver = $request->user();

        try {
            $stockOrder = StockOrder::with(['product'])
                ->where('id', $id)
                ->where('driver_id', $driver->id) // Ensure driver owns this order
                ->firstOrFail();

            // Check if order is still pending (can only cancel pending orders)
            if ($stockOrder->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending orders can be cancelled. Current status: ' . $stockOrder->status,
                    'current_status' => $stockOrder->status,
                ], 400);
            }

            // Delete the order (soft delete if you add SoftDeletes trait, otherwise hard delete)
            $stockOrder->delete();

            Log::info('Driver cancelled pending stock order', [
                'order_id' => $stockOrder->id,
                'driver_id' => $driver->id,
                'product_id' => $stockOrder->product_id,
                'quantity' => $stockOrder->quantity,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Stock order cancelled successfully',
                'data' => [
                    'order_id' => $stockOrder->id,
                    'product_name' => $stockOrder->product->name ?? 'Unknown',
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Stock order not found for cancellation', [
                'order_id' => $id,
                'driver_id' => $driver->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Stock order not found or you do not have permission to cancel it',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to cancel stock order: ' . $e->getMessage(), [
                'order_id' => $id,
                'driver_id' => $driver->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel stock order: ' . $e->getMessage(),
            ], 500);
        }
    }
}

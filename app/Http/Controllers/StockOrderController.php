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
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $driver = $request->user();

        // Verify product exists
        $product = Product::findOrFail($validated['product_id']);

        // Check if there's already a pending order for this product
        $existingOrder = StockOrder::where('driver_id', $driver->id)
            ->where('product_id', $validated['product_id'])
            ->where('status', 'pending')
            ->first();

        if ($existingOrder) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending order for this product',
                'data' => [
                    'order_id' => $existingOrder->id,
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
}

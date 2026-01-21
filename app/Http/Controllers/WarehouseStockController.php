<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\WarehouseStock;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\RoomStock;
use App\Models\ItemPlacement;
use App\Services\Packing\LAFFPackingService;
use App\Services\Layout\LayoutValidator;
use App\Services\Storage\StorageFeedbackService;
use App\Services\Storage\StorageSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WarehouseStockController extends Controller
{
    public function __construct(
        private StorageSuggestionService $suggestionService,
        private StorageFeedbackService $feedbackService,
        private LAFFPackingService $packingService,
        private LayoutValidator $layoutValidator
    ) {
    }

    public function index(): JsonResponse
    {
        try {
            $stock = WarehouseStock::with([
                'product.category' // Use dot notation for nested eager loading
            ])->get();

            // Handle cases where product might be null or category might be null
            $stock = $stock->map(function ($item) {
                if (!$item->product) {
                    // If product was deleted, skip it
                    return null;
                }
                // Ensure category is set even if null
                if (!$item->product->category && $item->product->category_id) {
                    // Category was deleted but product still references it
                    $item->product->category = null;
                }
                return $item;
            })->filter(); // Remove null items

            return response()->json($stock->values());
        } catch (\Exception $e) {
            Log::error('WarehouseStock index error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'error' => 'Failed to fetch warehouse stock',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:0',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $previousStock = WarehouseStock::where('product_id', $validated['product_id'])->first();
        $previousQuantity = $previousStock ? $previousStock->quantity : 0;
        $quantityAdded = max(0, $validated['quantity'] - $previousQuantity);

        $stock = WarehouseStock::updateOrCreate(
            ['product_id' => $validated['product_id']],
            ['quantity' => $validated['quantity']]
        );

        $response = [
            'success' => true,
            'message' => 'Stock updated successfully.',
            'stock_updated' => [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'new_quantity' => $validated['quantity'],
                'previous_quantity' => $previousQuantity,
                'quantity_added' => $quantityAdded,
            ],
        ];

        // Generate storage suggestions if quantity was added
        if ($quantityAdded > 0) {
            $suggestions = $this->suggestionService->generateSuggestions(
                $product->id,
                $quantityAdded
            );

            if (! isset($suggestions['error'])) {
                $feedback = $this->feedbackService->generateFeedback($suggestions);

                $response['message'] = 'Stock updated successfully. Storage suggestions available.';
                $response['storage_suggestions'] = array_merge($suggestions, $feedback);
            } else {
                $response['storage_suggestions'] = $suggestions;
            }
        }

        return response()->json($response);
    }

    public function suggestStorage(int $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $stock = WarehouseStock::where('product_id', $productId)->first();
        $quantity = $stock ? $stock->quantity : 0;

        if ($quantity <= 0) {
            return response()->json([
                'error' => 'No stock available',
                'message' => 'Product has no stock to suggest storage for',
            ], 400);
        }

        $suggestions = $this->suggestionService->generateSuggestions($productId, $quantity);

        if (isset($suggestions['error'])) {
            return response()->json($suggestions, 400);
        }

        $feedback = $this->feedbackService->generateFeedback($suggestions);

        return response()->json(array_merge($suggestions, $feedback));
    }

    public function applySuggestion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'product_id' => 'required|exists:products,id',
            'x_position' => 'required|numeric',
            'y_position' => 'required|numeric',
            'z_position' => 'required|numeric',
            'quantity' => 'required|integer|min:1',
        ]);

        // This would create actual placements in the room
        // For now, return success message
        return response()->json([
            'success' => true,
            'message' => 'Storage suggestion applied successfully',
            'placement' => $validated,
        ]);
    }

    public function pendingSuggestions(): JsonResponse
    {
        // Get products with stock but no room placements
        $products = Product::whereHas('warehouseStock', function ($query) {
            $query->where('quantity', '>', 0);
        })
            ->whereDoesntHave('productDimension')
            ->orWhereHas('productDimension')
            ->get();

        $suggestions = [];
        foreach ($products as $product) {
            $stock = $product->warehouseStock;
            if ($stock && $stock->quantity > 0) {
                $suggestions[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $stock->quantity,
                    'has_dimensions' => $product->productDimension !== null,
                ];
            }
        }

        return response()->json([
            'pending_suggestions' => $suggestions,
            'count' => count($suggestions),
        ]);
    }

    /**
     * Place newly added stock into a specific room using the packing service.
     *
     * This does NOT recompute the full layout; it only places the delta quantity
     * for a single product into the selected room and creates a new layout
     * record representing this placement batch.
     */
    public function placeIntoRoom(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $room = Room::findOrFail($validated['room_id']);
        $product = Product::findOrFail($validated['product_id']);

        // Ensure we have enough warehouse stock total to place
        $warehouseStock = WarehouseStock::where('product_id', $product->id)->first();
        $available = $warehouseStock?->quantity ?? 0;
        if ($available < $validated['quantity']) {
            return response()->json([
                'error' => 'Insufficient warehouse stock',
                'message' => "Only {$available} units available in warehouse for {$product->name}",
            ], 400);
        }

        $dimension = $product->productDimension;
        if (! $dimension) {
            return response()->json([
                'error' => 'Product dimensions not found',
                'product_id' => $product->id,
                'message' => "Product '{$product->name}' does not have dimensions defined",
            ], 400);
        }

        // Prepare a single-item batch for packing (delta only)
        $items = [[
            'product_id' => $product->id,
            'quantity' => $validated['quantity'],
            'width' => $dimension->width,
            'depth' => $dimension->depth,
            'height' => $dimension->height,
        ]];

        try {
            $result = $this->packingService->pack(
                $items,
                $room->width,
                $room->depth,
                $room->height,
                [
                    'prefer_bottom' => true,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Packing service error (placeIntoRoom)', [
                'room_id' => $room->id,
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to generate placement for room',
                'message' => $e->getMessage(),
            ], 500);
        }

        // Validate the resulting placements
        $validation = $this->layoutValidator->validateLayout(
            $result['placements'],
            $room->width,
            $room->depth,
            $room->height
        );

        if (! $validation['valid']) {
            Log::error('Layout validation failed (placeIntoRoom)', [
                'room_id' => $room->id,
                'product_id' => $product->id,
                'errors' => $validation['errors'],
            ]);

            return response()->json([
                'error' => 'Layout validation failed',
                'message' => 'The generated placement has validation errors.',
                'errors' => $validation['errors'],
            ], 400);
        }

        $placedCount = count($result['placements']);
        $attempted = $result['placements'] ? ($items[0]['quantity']) : 0;
        $unplacedCount = max(0, $validated['quantity'] - $placedCount);

        DB::beginTransaction();
        try {
            // Create a lightweight layout record representing this placement batch
            $layout = RoomLayout::create([
                'room_id' => $room->id,
                'algorithm_used' => 'laff_maxrects_delta',
                'utilization_percentage' => $result['utilization'],
                'total_items_placed' => $placedCount,
                'total_items_attempted' => $validated['quantity'],
                'layout_data' => [
                    'version' => '1.0',
                    'algorithm' => 'laff_maxrects_delta',
                    'utilization' => $result['utilization'],
                    'computed_at' => now()->toIso8601String(),
                    'placements' => $result['placements'],
                    'unplaced_items' => $result['unplaced_items'],
                ],
            ]);

            // Persist item placements
            foreach ($result['placements'] as $placement) {
                ItemPlacement::create([
                    'room_layout_id' => $layout->id,
                    'product_id' => $placement['product_id'],
                    'x_position' => $placement['x'],
                    'y_position' => $placement['y'],
                    'z_position' => $placement['z'],
                    'width' => $placement['width'],
                    'depth' => $placement['depth'],
                    'height' => $placement['height'],
                    'rotation' => '0',
                    'layer_index' => $placement['layer_index'] ?? 0,
                    'stack_id' => $placement['stack_id'] ?? null,
                    'stack_position' => $placement['stack_position'] ?? 1,
                    'stack_base_x' => $placement['stack_base_x'] ?? $placement['x'],
                    'stack_base_y' => $placement['stack_base_y'] ?? $placement['y'],
                    'items_below_count' => $placement['items_below_count'] ?? 0,
                ]);
            }

            // Update room_stocks for placed quantity
            if ($placedCount > 0) {
                $roomStock = RoomStock::firstOrCreate(
                    [
                        'room_id' => $room->id,
                        'product_id' => $product->id,
                    ],
                    ['quantity' => 0]
                );

                $roomStock->increment('quantity', $placedCount);
            }

            // Note: we do NOT change warehouse_stock here; that is handled by the main stock update.

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to save placements for placeIntoRoom', [
                'room_id' => $room->id,
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to save placements for room',
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'room_id' => $room->id,
            'product_id' => $product->id,
            'placed' => $placedCount,
            'unplaced' => $unplacedCount,
            'unplaced_items' => $result['unplaced_items'],
            'layout_id' => $layout->id,
            'message' => $unplacedCount > 0
                ? "Placed {$placedCount} items into room, {$unplacedCount} could not be placed."
                : "Successfully placed all {$placedCount} items into room.",
        ]);
    }
}

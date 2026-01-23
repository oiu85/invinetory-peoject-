<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateLayoutRequest;
use App\Models\ItemPlacement;
use App\Models\Product;
use App\Models\ProductDimension;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Services\Layout\LayoutValidator;
use App\Services\Packing\LAFFPackingService;
use App\Services\Packing\CompartmentPackingService;
use App\Services\Packing\CompartmentManager;
use App\Services\Validation\RoomValidationService;
use App\Services\Validation\StockValidationService;
use App\Models\WarehouseStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomLayoutController extends Controller
{
    public function __construct(
        private LAFFPackingService $packingService,
        private CompartmentPackingService $compartmentPackingService,
        private LayoutValidator $layoutValidator,
        private CompartmentManager $compartmentManager,
        private RoomValidationService $roomValidationService,
        private StockValidationService $stockValidationService
    ) {
    }

    public function show(int $id): JsonResponse
    {
        try {
            $room = Room::findOrFail($id);
            $layout = $room->layouts()->latest()->first();

            if (! $layout) {
                return response()->json([
                    'message' => 'No layout found for this room',
                    'room_id' => $id,
                ], 404);
            }

            // Return the layout with its data and placements
            $layout->load(['placements.product']);
            
            return response()->json([
                'id' => $layout->id,
                'room_id' => $layout->room_id,
                'algorithm_used' => $layout->algorithm_used,
                'utilization_percentage' => (float) $layout->utilization_percentage,
                'total_items_placed' => $layout->total_items_placed,
                'total_items_attempted' => $layout->total_items_attempted,
                'layout_data' => $layout->layout_data,
                'compartment_config' => $layout->compartment_config,
                'grid_columns' => $layout->grid_columns,
                'grid_rows' => $layout->grid_rows,
                'placements' => $layout->placements,
                'created_at' => $layout->created_at,
                'updated_at' => $layout->updated_at,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch layout',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate room and products before layout generation.
     */
    public function validateBeforeGeneration(Request $request, int $id): JsonResponse
    {
        try {
            $room = Room::findOrFail($id);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Room not found',
                'message' => $e->getMessage(),
            ], 404);
        }

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $items = $request->input('items', []);

        // Prepare items with dimensions
        $itemsWithDimensions = [];
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            if (!$product) continue;

            $dimension = $product->productDimension;
            if (!$dimension) {
                return response()->json([
                    'error' => 'Product dimensions not found',
                    'product_id' => $item['product_id'],
                    'message' => "Product '{$product->name}' does not have dimensions defined",
                ], 400);
            }

            $itemsWithDimensions[] = [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'width' => $dimension->width,
                'depth' => $dimension->depth,
                'height' => $dimension->height,
            ];
        }

        // Validate room for products
        $roomValidation = $this->roomValidationService->validateRoomForProducts($room, $itemsWithDimensions);

        // Validate stock
        $stockValidation = $this->stockValidationService->validateQuantitiesAgainstStock($items, $room->id);

        // Calculate theoretical capacity
        $capacity = $this->roomValidationService->calculateTheoreticalCapacity($room, $itemsWithDimensions);

        // Get suggestions
        $suggestions = $this->stockValidationService->suggestOptimalQuantities(
            $itemsWithDimensions,
            $room->width,
            $room->depth,
            $room->height,
            $room->id
        );

        // Pre-generation validation using LayoutValidator
        $preValidation = $this->layoutValidator->validateBeforeGeneration($room, $itemsWithDimensions);

        return response()->json([
            'valid' => $roomValidation['valid'] && $stockValidation['valid'],
            'room_validation' => $roomValidation,
            'stock_validation' => $stockValidation,
            'capacity' => $capacity,
            'suggestions' => $suggestions,
            'pre_generation_validation' => $preValidation,
        ]);
    }

    public function generate(GenerateLayoutRequest $request, int $id): JsonResponse
    {
        try {
            $room = Room::findOrFail($id);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Room not found',
                'message' => $e->getMessage(),
            ], 404);
        }

        // Log the incoming request for debugging
        \Log::info('Layout generation request', [
            'room_id' => $id,
            'request_data' => $request->all(),
        ]);

        // FormRequest automatically validates and throws ValidationException
        // If we reach here, validation passed
        $validated = $request->validated();
        $algorithm = $validated['algorithm'] ?? 'compartment';
        // Rotation is always disabled in simplified 2D mode
        $allowRotation = false;
        $items = $validated['items'];
        $options = $validated['options'] ?? [];

        // Validate quantities against warehouse stock (max per product)
        $preparedItems = [];
        foreach ($items as $item) {
            $product = Product::findOrFail($item['product_id']);
            $dimension = $product->productDimension;

            if (! $dimension) {
                return response()->json([
                    'error' => 'Product dimensions not found',
                    'product_id' => $item['product_id'],
                    'message' => "Product '{$product->name}' does not have dimensions defined",
                ], 400);
            }

            // Get max available quantity from warehouse stock
            $warehouseStock = WarehouseStock::where('product_id', $item['product_id'])->first();
            $maxQuantity = $warehouseStock?->quantity ?? 0;
            $requestedQuantity = (int) ($item['quantity'] ?? 0);

            if ($requestedQuantity > $maxQuantity) {
                return response()->json([
                    'error' => 'Insufficient warehouse stock',
                    'product_id' => $item['product_id'],
                    'product_name' => $product->name,
                    'message' => "Requested quantity ({$requestedQuantity}) exceeds available warehouse stock ({$maxQuantity})",
                    'requested_quantity' => $requestedQuantity,
                    'max_available' => $maxQuantity,
                ], 422);
            }

            $preparedItems[] = [
                'product_id' => $item['product_id'],
                'quantity' => $requestedQuantity,
                'width' => $dimension->width,
                'depth' => $dimension->depth,
                'height' => $dimension->height,
            ];
        }

        // Calculate expanded total for statistics
        $expandedTotal = 0;
        foreach ($preparedItems as $item) {
            $expandedTotal += $item['quantity'];
        }

        // Generate layout using compartment algorithm
        try {
            $packingOptions = [
                'prefer_bottom' => $options['prefer_bottom'] ?? true,
                'column_max_height' => $options['column_max_height'] ?? $room->height,
                'grid' => $options['grid'] ?? [],
            ];

            if ($algorithm === 'compartment' || $algorithm === 'compartment_grid') {
                $result = $this->compartmentPackingService->pack(
                    $preparedItems,
                    $room->width,
                    $room->depth,
                    $room->height,
                    $packingOptions
                );
            } else {
                // Fallback to LAFF for non-compartment algorithms
                $result = $this->packingService->pack(
                    $preparedItems,
                    $room->width,
                    $room->depth,
                    $room->height,
                    $packingOptions
                );
            }
        } catch (\Exception $e) {
            \Log::error('Packing service error', [
                'room_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Failed to generate layout',
                'message' => $e->getMessage(),
            ], 500);
        }

        // Validate layout
        $validation = $this->layoutValidator->validateLayout(
            $result['placements'],
            $room->width,
            $room->depth,
            $room->height
        );

        if (! $validation['valid']) {
            \Log::error('Layout validation failed', [
                'room_id' => $id,
                'errors' => $validation['errors'],
                'placements_count' => count($result['placements']),
                'sample_placement' => $result['placements'][0] ?? null,
            ]);
            
            return response()->json([
                'error' => 'Layout validation failed',
                'message' => 'The generated layout has validation errors. Please check the errors array for details.',
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'] ?? [],
            ], 400);
        }

        // Save layout to database
        DB::beginTransaction();
        try {
            $layoutData = [
                'version' => '1.0',
                'algorithm' => $algorithm,
                'utilization' => $result['utilization'],
                'computed_at' => now()->toIso8601String(),
                'placements' => $result['placements'],
                'unplaced_items' => $result['unplaced_items'],
            ];

            // Add compartment and grid data if using compartment algorithm
            if (isset($result['compartments']) && isset($result['grid'])) {
                $layoutData['compartments'] = $result['compartments'];
                $layoutData['grid'] = $result['grid'];
            }

            $layout = RoomLayout::create([
                'room_id' => $room->id,
                'algorithm_used' => $algorithm,
                'utilization_percentage' => $result['utilization'],
                'total_items_placed' => count($result['placements']),
                'total_items_attempted' => $expandedTotal,
                'layout_data' => $layoutData,
                'compartment_config' => isset($result['compartments']) ? ['compartments' => $result['compartments']] : null,
                'grid_columns' => $result['grid']['columns'] ?? null,
                'grid_rows' => $result['grid']['rows'] ?? null,
            ]);

            // Create item placements
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
                    'rotation' => '0', // Always no rotation in simplified 2D mode
                    'layer_index' => $placement['layer_index'] ?? 0,
                    'stack_id' => $placement['stack_id'] ?? null,
                    'stack_position' => $placement['stack_position'] ?? 1,
                    'stack_base_x' => $placement['stack_base_x'] ?? $placement['x'],
                    'stack_base_y' => $placement['stack_base_y'] ?? $placement['y'],
                    'items_below_count' => $placement['items_below_count'] ?? 0,
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to save layout',
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json($layout->load(['placements.product']), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $room = Room::findOrFail($id);
        $layout = $room->layouts()->latest()->first();

        if (! $layout) {
            return response()->json(['message' => 'No layout found for this room'], 404);
        }

        $validated = $request->validate([
            'layout_data' => 'required|array',
        ]);

        $layout->update([
            'layout_data' => $validated['layout_data'],
        ]);

        return response()->json($layout->load(['placements.product']));
    }

    public function optimize(int $id): JsonResponse
    {
        $room = Room::findOrFail($id);
        $layout = $room->layouts()->latest()->first();

        if (! $layout) {
            return response()->json(['message' => 'No layout found for this room'], 404);
        }

        // Get current placements as items
        $placements = $layout->placements;
        $items = [];

        foreach ($placements as $placement) {
            $items[] = [
                'product_id' => $placement->product_id,
                'quantity' => 1,
                'width' => $placement->width,
                'depth' => $placement->depth,
                'height' => $placement->height,
            ];
        }

        // Regenerate layout (simplified 2D mode: no rotation, floor-only)
        $result = $this->packingService->pack(
            $items,
            $room->width,
            $room->depth,
            $room->height,
            ['prefer_bottom' => true]
        );

        // Update layout
        DB::beginTransaction();
        try {
            $layout->placements()->delete();

            $layout->update([
                'utilization_percentage' => $result['utilization'],
                'total_items_placed' => count($result['placements']),
                'layout_data' => [
                    'version' => '1.0',
                    'algorithm' => $layout->algorithm_used,
                    'utilization' => $result['utilization'],
                    'computed_at' => now()->toIso8601String(),
                    'placements' => $result['placements'],
                ],
            ]);

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
                    'rotation' => '0', // Always no rotation in simplified 2D mode
                    'layer_index' => $placement['layer_index'] ?? 0,
                    'stack_id' => $placement['stack_id'] ?? null,
                    'stack_position' => $placement['stack_position'] ?? 1,
                    'stack_base_x' => $placement['stack_base_x'] ?? $placement['x'],
                    'stack_base_y' => $placement['stack_base_y'] ?? $placement['y'],
                    'items_below_count' => $placement['items_below_count'] ?? 0,
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to optimize layout',
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json($layout->load(['placements.product']));
    }

    public function destroy(int $id): JsonResponse
    {
        $room = Room::findOrFail($id);
        $layout = $room->layouts()->latest()->first();

        if (! $layout) {
            return response()->json(['message' => 'No layout found for this room'], 404);
        }

        $layout->delete();

        return response()->json(['message' => 'Layout deleted successfully']);
    }

    public function placements(int $id): JsonResponse
    {
        try {
            $room = Room::findOrFail($id);
            $layout = $room->layouts()->latest()->first();

            if (! $layout) {
                return response()->json([]);
            }

            $placements = $layout->placements()->with('product')->get();

            return response()->json($placements->toArray());
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch placements',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function addPlacement(Request $request, int $id): JsonResponse
    {
        $room = Room::findOrFail($id);
        $layout = $room->layouts()->latest()->first();

        if (! $layout) {
            return response()->json(['message' => 'No layout found for this room'], 404);
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'x_position' => 'required|numeric|min:0',
            'y_position' => 'required|numeric|min:0',
            'z_position' => 'required|numeric|min:0',
            'width' => 'required|numeric|min:0.01',
            'depth' => 'required|numeric|min:0.01',
            'height' => 'required|numeric|min:0.01',
            'rotation' => 'nullable|in:0,90,180,270',
        ]);

        $placement = ItemPlacement::create([
            'room_layout_id' => $layout->id,
            ...$validated,
            'rotation' => '0', // Always no rotation in simplified 2D mode
            'layer_index' => 0,
        ]);

        return response()->json($placement->load('product'), 201);
    }

    public function updatePlacement(Request $request, int $id): JsonResponse
    {
        $placement = ItemPlacement::findOrFail($id);

        $validated = $request->validate([
            'x_position' => 'sometimes|numeric|min:0',
            'y_position' => 'sometimes|numeric|min:0',
            'z_position' => 'sometimes|numeric|min:0',
            'width' => 'sometimes|numeric|min:0.01',
            'depth' => 'sometimes|numeric|min:0.01',
            'height' => 'sometimes|numeric|min:0.01',
            'rotation' => 'sometimes|in:0,90,180,270',
        ]);

        // Force rotation to '0' in simplified 2D mode
        $validated['rotation'] = '0';

        $placement->update($validated);

        return response()->json($placement->load('product'));
    }

    public function deletePlacement(int $id): JsonResponse
    {
        $placement = ItemPlacement::findOrFail($id);
        $placement->delete();

        return response()->json(['message' => 'Placement deleted successfully']);
    }

    /**
     * Refresh layout for a room (recalculate based on current room stock).
     */
    public function refresh(int $id): JsonResponse
    {
        $room = Room::findOrFail($id);

        // Get current room stock
        $roomStocks = \App\Models\RoomStock::where('room_id', $room->id)
            ->where('quantity', '>', 0)
            ->with('product.productDimension')
            ->get();

        if ($roomStocks->isEmpty()) {
            return response()->json([
                'message' => 'No stock in room to generate layout',
                'room_id' => $room->id,
            ], 400);
        }

        // Prepare items from room stock
        $items = [];
        foreach ($roomStocks as $roomStock) {
            $dimension = $roomStock->product->productDimension;
            if (!$dimension) {
                continue;
            }

            $items[] = [
                'product_id' => $roomStock->product_id,
                'quantity' => $roomStock->quantity,
                'width' => $dimension->width,
                'depth' => $dimension->depth,
                'height' => $dimension->height,
            ];
        }

        if (empty($items)) {
            return response()->json([
                'message' => 'No products with dimensions in room',
                'room_id' => $room->id,
            ], 400);
        }

        // Generate new layout using compartment algorithm
        try {
            $result = $this->compartmentPackingService->pack(
                $items,
                $room->width,
                $room->depth,
                $room->height,
                ['prefer_bottom' => true]
            );
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to refresh layout',
                'message' => $e->getMessage(),
            ], 500);
        }

        // Save new layout
        DB::beginTransaction();
        try {
            // Delete old layouts
            $room->layouts()->delete();

            $layout = RoomLayout::create([
                'room_id' => $room->id,
                'algorithm_used' => 'compartment',
                'utilization_percentage' => $result['utilization'],
                'total_items_placed' => count($result['placements']),
                'total_items_attempted' => array_sum(array_column($items, 'quantity')),
                'layout_data' => [
                    'version' => '1.0',
                    'algorithm' => 'compartment',
                    'utilization' => $result['utilization'],
                    'computed_at' => now()->toIso8601String(),
                    'placements' => $result['placements'],
                    'unplaced_items' => $result['unplaced_items'],
                    'compartments' => $result['compartments'] ?? [],
                    'grid' => $result['grid'] ?? [],
                ],
                'compartment_config' => isset($result['compartments']) ? ['compartments' => $result['compartments']] : null,
                'grid_columns' => $result['grid']['columns'] ?? null,
                'grid_rows' => $result['grid']['rows'] ?? null,
            ]);

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

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to save refreshed layout',
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json($layout->load(['placements.product']));
    }
}

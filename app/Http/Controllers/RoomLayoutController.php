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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomLayoutController extends Controller
{
    public function __construct(
        private LAFFPackingService $packingService,
        private LayoutValidator $layoutValidator
    ) {
    }

    public function show(int $id): JsonResponse
    {
        $room = Room::findOrFail($id);
        $layout = $room->layouts()->latest()->first();

        if (! $layout) {
            return response()->json(['message' => 'No layout found for this room'], 404);
        }

        return response()->json($layout->load(['placements.product']));
    }

    public function generate(GenerateLayoutRequest $request, int $id): JsonResponse
    {
        $room = Room::findOrFail($id);

        $validated = $request->validated();
        $algorithm = $validated['algorithm'] ?? 'laff_maxrects';
        $allowRotation = $validated['allow_rotation'] ?? true;
        $items = $validated['items'];
        $options = $validated['options'] ?? [];

        // Prepare items with dimensions
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

            $preparedItems[] = [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'width' => $item['dimensions']['width'] ?? $dimension->width,
                'depth' => $item['dimensions']['depth'] ?? $dimension->depth,
                'height' => $item['dimensions']['height'] ?? $dimension->height,
                'rotatable' => $dimension->rotatable,
            ];
        }

        // Generate layout
        $result = $this->packingService->pack(
            $preparedItems,
            $room->width,
            $room->depth,
            $room->height,
            [
                'allow_rotation' => $allowRotation,
                'prefer_bottom' => $options['prefer_bottom'] ?? true,
            ]
        );

        // Validate layout
        $validation = $this->layoutValidator->validateLayout(
            $result['placements'],
            $room->width,
            $room->depth,
            $room->height
        );

        if (! $validation['valid']) {
            return response()->json([
                'error' => 'Layout validation failed',
                'errors' => $validation['errors'],
            ], 400);
        }

        // Save layout to database
        DB::beginTransaction();
        try {
            $layout = RoomLayout::create([
                'room_id' => $room->id,
                'algorithm_used' => $algorithm,
                'utilization_percentage' => $result['utilization'],
                'total_items_placed' => count($result['placements']),
                'total_items_attempted' => count($preparedItems),
                'layout_data' => [
                    'version' => '1.0',
                    'algorithm' => $algorithm,
                    'utilization' => $result['utilization'],
                    'computed_at' => now()->toIso8601String(),
                    'placements' => $result['placements'],
                    'unplaced_items' => $result['unplaced_items'],
                ],
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
                    'rotation' => $placement['rotation'],
                    'layer_index' => $placement['layer_index'],
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
                'rotatable' => true,
            ];
        }

        // Regenerate layout
        $result = $this->packingService->pack(
            $items,
            $room->width,
            $room->depth,
            $room->height,
            ['allow_rotation' => true, 'prefer_bottom' => true]
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
                    'rotation' => $placement['rotation'],
                    'layer_index' => $placement['layer_index'],
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
        $room = Room::findOrFail($id);
        $layout = $room->layouts()->latest()->first();

        if (! $layout) {
            return response()->json(['placements' => []]);
        }

        $placements = $layout->placements()->with('product')->get();

        return response()->json($placements);
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
            'rotation' => $validated['rotation'] ?? '0',
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

        $placement->update($validated);

        return response()->json($placement->load('product'));
    }

    public function deletePlacement(int $id): JsonResponse
    {
        $placement = ItemPlacement::findOrFail($id);
        $placement->delete();

        return response()->json(['message' => 'Placement deleted successfully']);
    }
}

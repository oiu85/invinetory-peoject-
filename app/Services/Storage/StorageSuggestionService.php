<?php

namespace App\Services\Storage;

use App\Models\ItemPlacement;
use App\Models\Product;
use App\Models\ProductDimension;
use App\Models\Room;
use App\Services\Layout\LayoutValidator;
use App\Services\Packing\LAFFPackingService;

class StorageSuggestionService
{
    public function __construct(
        private LAFFPackingService $packingService,
        private VerticalStackingService $stackingService,
        private LayoutValidator $layoutValidator
    ) {
    }

    /**
     * Generate storage suggestions for a product when stock is updated.
     *
     * @return array
     */
    public function generateSuggestions(
        int $productId,
        int $quantityAdded
    ): array {
        $product = Product::findOrFail($productId);
        $dimension = $product->productDimension;

        if (! $dimension) {
            return [
                'error' => 'Product dimensions not found',
                'message' => "Product '{$product->name}' does not have dimensions defined",
            ];
        }

        $rooms = Room::where('status', 'active')->get();

        if ($rooms->isEmpty()) {
            return [
                'error' => 'No active rooms available',
                'message' => 'No active rooms found in the warehouse',
            ];
        }

        $suggestions = [];
        $bestRoom = null;
        $bestScore = 0;

        foreach ($rooms as $room) {
            $roomSuggestions = $this->analyzeRoom(
                $room,
                $productId,
                $dimension,
                $quantityAdded
            );

            if (! empty($roomSuggestions['placement_options'])) {
                $suggestions[] = $roomSuggestions;

                // Score room based on utilization and stack availability
                $score = $this->scoreRoom($roomSuggestions);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestRoom = $roomSuggestions;
                }
            }
        }

        return [
            'recommended_room' => $bestRoom ? [
                'room_id' => $bestRoom['room_id'],
                'room_name' => $bestRoom['room_name'],
                'priority' => 'high',
                'reason' => 'Optimal space utilization and existing product stack available',
            ] : null,
            'placement_options' => $bestRoom['placement_options'] ?? [],
            'alternative_rooms' => array_filter($suggestions, function ($s) use ($bestRoom) {
                return $s['room_id'] !== ($bestRoom['room_id'] ?? null);
            }),
        ];
    }

    /**
     * Analyze a room for storage suggestions.
     *
     * @return array
     */
    private function analyzeRoom(
        Room $room,
        int $productId,
        ProductDimension $dimension,
        int $quantity
    ): array {
        $options = [];

        // Check existing stacks
        $existingStacks = $this->findExistingStacks($room->id, $productId);

        foreach ($existingStacks as $stack) {
            $capacity = $this->stackingService->calculateStackCapacity(
                $room->id,
                $productId,
                $stack['x'],
                $stack['y'],
                $dimension->height,
                $room->height
            );

            if ($capacity > 0) {
                $zPosition = $this->stackingService->calculateStackZPosition(
                    $room->id,
                    $productId,
                    $stack['x'],
                    $stack['y'],
                    $dimension->height
                );

                $canFit = min($capacity, $quantity);

                $options[] = [
                    'room_id' => $room->id,
                    'room_name' => $room->name,
                    'x_position' => $stack['x'],
                    'y_position' => $stack['y'],
                    'z_position' => $zPosition,
                    'stack_on_existing' => true,
                    'existing_stack_height' => $stack['stack_height'],
                    'items_in_stack' => $stack['items_count'],
                    'max_stack_height' => $room->height,
                    'remaining_stack_capacity' => $room->height - $stack['stack_height'],
                    'can_fit_quantity' => $canFit,
                    'utilization_after' => 0, // Will be calculated
                    'visualization' => [
                        'color' => '#4CAF50',
                        'position_label' => "Stack Position {$stack['items_count'] + 1}-" . ($stack['items_count'] + $canFit),
                    ],
                ];
            }
        }

        // Find new positions using LAFF algorithm
        $newPositions = $this->findNewPositions($room, $productId, $dimension, $quantity);

        $options = array_merge($options, $newPositions);

        return [
            'room_id' => $room->id,
            'room_name' => $room->name,
            'placement_options' => $options,
        ];
    }

    /**
     * Find existing stacks for a product in a room.
     *
     * @return array
     */
    private function findExistingStacks(int $roomId, int $productId): array
    {
        $stacks = ItemPlacement::whereHas('roomLayout', function ($query) use ($roomId) {
            $query->where('room_id', $roomId);
        })
            ->where('product_id', $productId)
            ->whereNotNull('stack_base_x')
            ->whereNotNull('stack_base_y')
            ->select('stack_base_x', 'stack_base_y')
            ->distinct()
            ->get();

        $result = [];
        foreach ($stacks as $stack) {
            $stackInfo = $this->stackingService->findExistingStack(
                $roomId,
                $productId,
                $stack->stack_base_x,
                $stack->stack_base_y
            );

            if ($stackInfo) {
                $result[] = [
                    'x' => $stack->stack_base_x,
                    'y' => $stack->stack_base_y,
                    'stack_height' => $stackInfo['stack_height'],
                    'items_count' => $stackInfo['items_count'],
                ];
            }
        }

        return $result;
    }

    /**
     * Find new positions using LAFF algorithm.
     *
     * @return array
     */
    private function findNewPositions(
        Room $room,
        int $productId,
        ProductDimension $dimension,
        int $quantity
    ): array {
        $items = [[
            'product_id' => $productId,
            'quantity' => $quantity,
            'width' => $dimension->width,
            'depth' => $dimension->depth,
            'height' => $dimension->height,
            'rotatable' => $dimension->rotatable,
        ]];

        $result = $this->packingService->pack(
            $items,
            $room->width,
            $room->depth,
            $room->height,
            ['allow_rotation' => $dimension->rotatable, 'prefer_bottom' => true]
        );

        $options = [];
        foreach ($result['placements'] as $placement) {
            $options[] = [
                'room_id' => $room->id,
                'room_name' => $room->name,
                'x_position' => $placement['x'],
                'y_position' => $placement['y'],
                'z_position' => 0,
                'stack_on_existing' => false,
                'new_stack' => true,
                'can_fit_quantity' => 1,
                'utilization_after' => $result['utilization'],
                'visualization' => [
                    'color' => '#2196F3',
                    'position_label' => 'New Stack Position',
                ],
            ];
        }

        return $options;
    }

    /**
     * Score a room suggestion.
     */
    private function scoreRoom(array $roomSuggestion): float
    {
        $score = 0.0;

        foreach ($roomSuggestion['placement_options'] as $option) {
            if ($option['stack_on_existing'] ?? false) {
                $score += 10; // Prefer existing stacks
            }
            $score += $option['utilization_after'] ?? 0;
        }

        return $score;
    }
}

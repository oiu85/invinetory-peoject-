<?php

namespace App\Services\Storage;

use App\Models\ItemPlacement;

class VerticalStackingService
{
    /**
     * Find existing stack for a product at given coordinates.
     *
     * @return array{stack_height: float, items_count: int, items: array}|null
     */
    public function findExistingStack(
        int $roomId,
        int $productId,
        float $x,
        float $y
    ): ?array {
        $placements = ItemPlacement::whereHas('roomLayout', function ($query) use ($roomId) {
            $query->where('room_id', $roomId);
        })
            ->where('product_id', $productId)
            ->where('stack_base_x', $x)
            ->where('stack_base_y', $y)
            ->orderBy('stack_position')
            ->get();

        if ($placements->isEmpty()) {
            return null;
        }

        $stackHeight = 0.0;
        foreach ($placements as $placement) {
            $stackHeight += $placement->height;
        }

        return [
            'stack_height' => $stackHeight,
            'items_count' => $placements->count(),
            'items' => $placements->toArray(),
        ];
    }

    /**
     * Calculate Z position for stacking on existing stack.
     */
    public function calculateStackZPosition(
        int $roomId,
        int $productId,
        float $x,
        float $y,
        float $itemHeight
    ): ?float {
        $stack = $this->findExistingStack($roomId, $productId, $x, $y);

        if ($stack === null) {
            return 0.0; // Ground level for new stack
        }

        return $stack['stack_height'];
    }

    /**
     * Check if item can fit in existing stack.
     */
    public function canFitInStack(
        int $roomId,
        int $productId,
        float $x,
        float $y,
        float $itemHeight,
        float $roomHeight
    ): bool {
        $stack = $this->findExistingStack($roomId, $productId, $x, $y);

        if ($stack === null) {
            return $itemHeight <= $roomHeight;
        }

        $availableHeight = $roomHeight - $stack['stack_height'];

        return $itemHeight <= $availableHeight;
    }

    /**
     * Calculate how many items can fit in a stack.
     */
    public function calculateStackCapacity(
        int $roomId,
        int $productId,
        float $x,
        float $y,
        float $itemHeight,
        float $roomHeight
    ): int {
        $stack = $this->findExistingStack($roomId, $productId, $x, $y);

        if ($stack === null) {
            return (int) floor($roomHeight / $itemHeight);
        }

        $availableHeight = $roomHeight - $stack['stack_height'];

        return (int) floor($availableHeight / $itemHeight);
    }

    /**
     * Get next stack position number.
     */
    public function getNextStackPosition(
        int $roomId,
        int $productId,
        float $x,
        float $y
    ): int {
        $stack = $this->findExistingStack($roomId, $productId, $x, $y);

        if ($stack === null) {
            return 1; // First item in new stack
        }

        return $stack['items_count'] + 1;
    }

    /**
     * Generate stack ID (using hash of coordinates and product).
     */
    public function generateStackId(int $productId, float $x, float $y): int
    {
        return crc32("{$productId}_{$x}_{$y}");
    }
}

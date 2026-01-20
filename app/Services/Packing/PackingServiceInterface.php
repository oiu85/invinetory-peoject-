<?php

namespace App\Services\Packing;

interface PackingServiceInterface
{
    /**
     * Pack items into a room.
     *
     * @param array $items Array of items to pack
     * @param float $roomWidth Room width
     * @param float $roomDepth Room depth
     * @param float $roomHeight Room height
     * @param array $options Additional options
     * @return array{placements: array, unplaced_items: array, utilization: float}
     */
    public function pack(
        array $items,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight,
        array $options = []
    ): array;
}

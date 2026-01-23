<?php

namespace App\Services\Validation;

use App\Models\Room;
use App\Models\Product;
use App\Models\ProductDimension;

class RoomValidationService
{
    /**
     * Validate if room can accommodate the given products.
     *
     * @param Room $room
     * @param array $items Array of items with: product_id, quantity, width, depth, height
     * @return array{valid: bool, errors: array, warnings: array, capacity: array}
     */
    public function validateRoomForProducts(Room $room, array $items): array
    {
        $errors = [];
        $warnings = [];
        $capacity = [];

        // Validate room dimensions
        if ($room->width <= 0 || $room->depth <= 0 || $room->height <= 0) {
            $errors[] = 'Room has invalid dimensions';
            return [
                'valid' => false,
                'errors' => $errors,
                'warnings' => $warnings,
                'capacity' => $capacity,
            ];
        }

        $roomVolume = $room->width * $room->depth * $room->height;
        $roomFloorArea = $room->width * $room->depth;

        $totalVolumeNeeded = 0;
        $totalFloorAreaNeeded = 0;
        $productsTooLarge = [];
        $productsTooHigh = [];

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            $quantity = (int)($item['quantity'] ?? 0);
            $width = (float)($item['width'] ?? 0);
            $depth = (float)($item['depth'] ?? 0);
            $height = (float)($item['height'] ?? 0);

            if (!$productId || $quantity <= 0) {
                continue;
            }

            // Check if product dimensions are valid
            if ($width <= 0 || $depth <= 0 || $height <= 0) {
                $errors[] = "Product #{$productId} has invalid dimensions";
                continue;
            }

            // Check if product fits in room (individual item)
            $productFits = $this->validateProductFitsInRoom($room, $width, $depth, $height);
            if (!$productFits['fits']) {
                $productsTooLarge[] = [
                    'product_id' => $productId,
                    'reason' => $productFits['reason'],
                    'dimensions' => "{$width}×{$depth}×{$height} cm",
                ];
                $errors[] = "Product #{$productId} ({$width}×{$depth}×{$height} cm) {$productFits['reason']}";
                continue;
            }

            // Check height
            if ($height > $room->height) {
                $productsTooHigh[] = [
                    'product_id' => $productId,
                    'height' => $height,
                    'room_height' => $room->height,
                ];
                $errors[] = "Product #{$productId} height ({$height} cm) exceeds room height ({$room->height} cm)";
                continue;
            }

            // Calculate volume and floor area needed
            $itemVolume = $width * $depth * $height;
            $itemFloorArea = $width * $depth;

            $totalVolumeNeeded += $itemVolume * $quantity;
            $totalFloorAreaNeeded += $itemFloorArea * $quantity;

            // Calculate capacity for this product
            $maxQuantity = $this->calculateMaxQuantity($room, $width, $depth, $height);
            $capacity[$productId] = [
                'max_quantity' => $maxQuantity,
                'requested' => $quantity,
                'fits' => $quantity <= $maxQuantity,
            ];

            if ($quantity > $maxQuantity) {
                $warnings[] = "Product #{$productId}: Requested quantity ({$quantity}) exceeds estimated capacity ({$maxQuantity})";
            }
        }

        // Check total volume
        if ($totalVolumeNeeded > $roomVolume * 1.1) { // 10% tolerance for stacking
            $warnings[] = "Total volume needed ({$totalVolumeNeeded} cm³) exceeds room volume ({$roomVolume} cm³)";
        }

        // Check total floor area
        if ($totalFloorAreaNeeded > $roomFloorArea * 1.2) { // 20% tolerance for overlapping
            $warnings[] = "Total floor area needed ({$totalFloorAreaNeeded} cm²) significantly exceeds room floor area ({$roomFloorArea} cm²)";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'capacity' => $capacity,
            'room_volume' => $roomVolume,
            'room_floor_area' => $roomFloorArea,
            'total_volume_needed' => $totalVolumeNeeded,
            'total_floor_area_needed' => $totalFloorAreaNeeded,
        ];
    }

    /**
     * Validate if a single product fits in room.
     *
     * @param Room $room
     * @param float $width Product width
     * @param float $depth Product depth
     * @param float $height Product height
     * @return array{fits: bool, reason: string|null}
     */
    public function validateProductFitsInRoom(Room $room, float $width, float $depth, float $height): array
    {
        if ($width <= 0 || $depth <= 0 || $height <= 0) {
            return [
                'fits' => false,
                'reason' => 'Product has invalid dimensions',
            ];
        }

        if ($width > $room->width) {
            return [
                'fits' => false,
                'reason' => "Product width ({$width} cm) exceeds room width ({$room->width} cm)",
            ];
        }

        if ($depth > $room->depth) {
            return [
                'fits' => false,
                'reason' => "Product depth ({$depth} cm) exceeds room depth ({$room->depth} cm)",
            ];
        }

        if ($height > $room->height) {
            return [
                'fits' => false,
                'reason' => "Product height ({$height} cm) exceeds room height ({$room->height} cm)",
            ];
        }

        return [
            'fits' => true,
            'reason' => null,
        ];
    }

    /**
     * Calculate maximum quantity of a product that can fit in room.
     *
     * @param Room $room
     * @param float $width Product width
     * @param float $depth Product depth
     * @param float $height Product height
     * @return int Maximum quantity
     */
    public function calculateMaxQuantity(Room $room, float $width, float $depth, float $height): int
    {
        if ($width <= 0 || $depth <= 0 || $height <= 0) {
            return 0;
        }

        // Check if product fits at all
        if ($width > $room->width || $depth > $room->depth || $height > $room->height) {
            return 0;
        }

        // Calculate floor capacity (2D)
        $itemsPerRow = max(1, (int)floor($room->width / $width));
        $itemsPerColumn = max(1, (int)floor($room->depth / $depth));
        $floorCapacity = $itemsPerRow * $itemsPerColumn;

        // Calculate vertical stacking capacity
        $maxStackHeight = (int)floor($room->height / $height);
        $maxStackHeight = max(1, $maxStackHeight);

        // Total capacity
        $totalCapacity = $floorCapacity * $maxStackHeight;

        // Apply safety factor (80% to account for gaps and inefficiencies)
        return (int)floor($totalCapacity * 0.8);
    }

    /**
     * Calculate theoretical capacity for multiple products.
     *
     * @param Room $room
     * @param array $items Array of items with: product_id, width, depth, height, quantity
     * @return array{theoretical_capacity: int, estimated_utilization: float, strategy: string}
     */
    public function calculateTheoreticalCapacity(Room $room, array $items): array
    {
        $roomVolume = $room->width * $room->depth * $room->height;
        $roomFloorArea = $room->width * $room->depth;

        $totalVolume = 0;
        $totalFloorArea = 0;
        $totalItems = 0;

        foreach ($items as $item) {
            $width = (float)($item['width'] ?? 0);
            $depth = (float)($item['depth'] ?? 0);
            $height = (float)($item['height'] ?? 0);
            $quantity = (int)($item['quantity'] ?? 0);

            if ($width <= 0 || $depth <= 0 || $height <= 0 || $quantity <= 0) {
                continue;
            }

            $itemVolume = $width * $depth * $height;
            $itemFloorArea = $width * $depth;

            $totalVolume += $itemVolume * $quantity;
            $totalFloorArea += $itemFloorArea * $quantity;
            $totalItems += $quantity;
        }

        $volumeUtilization = $roomVolume > 0 ? ($totalVolume / $roomVolume) * 100 : 0;
        $floorUtilization = $roomFloorArea > 0 ? ($totalFloorArea / $roomFloorArea) * 100 : 0;

        // Determine strategy
        $strategy = 'mixed';
        if ($floorUtilization > 90) {
            $strategy = 'stacking_required';
        } elseif ($volumeUtilization > 80) {
            $strategy = 'dense_packing';
        } elseif ($totalItems < 10) {
            $strategy = 'sparse_packing';
        }

        return [
            'theoretical_capacity' => $totalItems,
            'estimated_utilization' => max($volumeUtilization, $floorUtilization),
            'volume_utilization' => $volumeUtilization,
            'floor_utilization' => $floorUtilization,
            'strategy' => $strategy,
            'total_volume' => $totalVolume,
            'total_floor_area' => $totalFloorArea,
            'room_volume' => $roomVolume,
            'room_floor_area' => $roomFloorArea,
        ];
    }

    /**
     * Validate room dimensions are reasonable.
     *
     * @param Room $room
     * @return array{valid: bool, errors: array}
     */
    public function validateRoomDimensions(Room $room): array
    {
        $errors = [];

        if ($room->width <= 0) {
            $errors[] = 'Room width must be greater than 0';
        }

        if ($room->depth <= 0) {
            $errors[] = 'Room depth must be greater than 0';
        }

        if ($room->height <= 0) {
            $errors[] = 'Room height must be greater than 0';
        }

        // Reasonable limits (adjust as needed)
        if ($room->width > 10000) { // 100 meters
            $errors[] = 'Room width is unreasonably large';
        }

        if ($room->depth > 10000) {
            $errors[] = 'Room depth is unreasonably large';
        }

        if ($room->height > 1000) { // 10 meters
            $errors[] = 'Room height is unreasonably large';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}

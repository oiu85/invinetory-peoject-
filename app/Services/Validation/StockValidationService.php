<?php

namespace App\Services\Validation;

use App\Models\WarehouseStock;
use App\Models\RoomStock;
use App\Models\Product;

class StockValidationService
{
    /**
     * Validate quantities against available stock.
     *
     * @param array $items Array of items with: product_id, quantity
     * @param int|null $roomId Optional room ID to check room stock
     * @return array{valid: bool, errors: array, available_quantities: array, suggestions: array}
     */
    public function validateQuantitiesAgainstStock(array $items, ?int $roomId = null): array
    {
        $errors = [];
        $availableQuantities = [];
        $suggestions = [];

        foreach ($items as $item) {
            $productId = (int)($item['product_id'] ?? 0);
            $requestedQuantity = (int)($item['quantity'] ?? 0);

            if ($productId <= 0 || $requestedQuantity <= 0) {
                continue;
            }

            $stockInfo = $this->getAvailableStockForProduct($productId, $roomId);
            $availableQuantities[$productId] = $stockInfo;

            // Check warehouse stock (primary source)
            if ($requestedQuantity > $stockInfo['warehouse_available']) {
                $errors[] = "Product #{$productId}: Requested quantity ({$requestedQuantity}) exceeds available warehouse stock ({$stockInfo['warehouse_available']})";
            }

            // Check room stock if applicable
            if ($roomId && $requestedQuantity > $stockInfo['room_available']) {
                $warnings[] = "Product #{$productId}: Requested quantity ({$requestedQuantity}) exceeds available room stock ({$stockInfo['room_available']})";
            }

            // Generate suggestion if requested exceeds available
            if ($requestedQuantity > $stockInfo['total_available']) {
                $suggestions[$productId] = [
                    'requested' => $requestedQuantity,
                    'available' => $stockInfo['total_available'],
                    'suggested' => $stockInfo['total_available'],
                    'reason' => 'Requested quantity exceeds available stock',
                ];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'available_quantities' => $availableQuantities,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Get available stock for a single product.
     *
     * @param int $productId
     * @param int|null $roomId
     * @return array{warehouse_available: int, room_available: int, total_available: int, warehouse_stock: int, room_stock: int}
     */
    public function getAvailableStockForProduct(int $productId, ?int $roomId = null): array
    {
        $warehouseStock = WarehouseStock::where('product_id', $productId)->first();
        $warehouseAvailable = $warehouseStock?->quantity ?? 0;

        $roomAvailable = 0;
        if ($roomId) {
            $roomStock = RoomStock::where('room_id', $roomId)
                ->where('product_id', $productId)
                ->first();
            $roomAvailable = $roomStock?->quantity ?? 0;
        }

        return [
            'warehouse_available' => $warehouseAvailable,
            'room_available' => $roomAvailable,
            'total_available' => $warehouseAvailable + $roomAvailable,
            'warehouse_stock' => $warehouseAvailable,
            'room_stock' => $roomAvailable,
        ];
    }

    /**
     * Get available stock for multiple products.
     *
     * @param array $productIds
     * @param int|null $roomId
     * @return array<int, array>
     */
    public function getAvailableStockForProducts(array $productIds, ?int $roomId = null): array
    {
        $result = [];

        // Fetch warehouse stock for all products at once
        $warehouseStocks = WarehouseStock::whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');

        $roomStocks = [];
        if ($roomId) {
            $roomStocks = RoomStock::where('room_id', $roomId)
                ->whereIn('product_id', $productIds)
                ->get()
                ->keyBy('product_id');
        }

        foreach ($productIds as $productId) {
            $warehouseStock = $warehouseStocks->get($productId);
            $roomStock = $roomStocks->get($productId) ?? null;

            $result[$productId] = [
                'warehouse_available' => $warehouseStock?->quantity ?? 0,
                'room_available' => $roomStock?->quantity ?? 0,
                'total_available' => ($warehouseStock?->quantity ?? 0) + ($roomStock?->quantity ?? 0),
                'warehouse_stock' => $warehouseStock?->quantity ?? 0,
                'room_stock' => $roomStock?->quantity ?? 0,
            ];
        }

        return $result;
    }

    /**
     * Suggest optimal quantities based on available stock and room capacity.
     *
     * @param array $items Array of items with: product_id, width, depth, height
     * @param float $roomWidth
     * @param float $roomDepth
     * @param float $roomHeight
     * @param int|null $roomId
     * @return array<int, array>
     */
    public function suggestOptimalQuantities(
        array $items,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight,
        ?int $roomId = null
    ): array {
        $suggestions = [];
        $productIds = array_filter(array_column($items, 'product_id'));
        $stockInfo = $this->getAvailableStockForProducts($productIds, $roomId);

        foreach ($items as $item) {
            $productId = (int)($item['product_id'] ?? 0);
            if ($productId <= 0) continue;

            $width = (float)($item['width'] ?? 0);
            $depth = (float)($item['depth'] ?? 0);
            $height = (float)($item['height'] ?? 0);

            if ($width <= 0 || $depth <= 0 || $height <= 0) {
                continue;
            }

            $available = $stockInfo[$productId]['total_available'] ?? 0;

            // Calculate theoretical capacity
            $itemsPerRow = max(1, (int)floor($roomWidth / $width));
            $itemsPerColumn = max(1, (int)floor($roomDepth / $depth));
            $maxStackHeight = max(1, (int)floor($roomHeight / $height));
            $theoreticalCapacity = (int)floor($itemsPerRow * $itemsPerColumn * $maxStackHeight * 0.8); // 80% efficiency

            // Suggest optimal quantity (min of available and capacity)
            $suggested = min($available, $theoreticalCapacity);

            $suggestions[$productId] = [
                'suggested_quantity' => $suggested,
                'available_stock' => $available,
                'theoretical_capacity' => $theoreticalCapacity,
                'limited_by' => $suggested === $available ? 'stock' : 'capacity',
            ];
        }

        return $suggestions;
    }

    /**
     * Check if all products have sufficient stock.
     *
     * @param array $items
     * @param int|null $roomId
     * @return bool
     */
    public function hasSufficientStock(array $items, ?int $roomId = null): bool
    {
        $validation = $this->validateQuantitiesAgainstStock($items, $roomId);
        return $validation['valid'];
    }
}

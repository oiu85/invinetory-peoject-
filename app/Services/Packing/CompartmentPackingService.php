<?php

namespace App\Services\Packing;

use App\Algorithms\Spatial\Box;
use App\Services\Packing\PackingServiceInterface;
use App\Services\Spatial\CollisionDetector;

class CompartmentPackingService implements PackingServiceInterface
{
    public function __construct(
        private CollisionDetector $collisionDetector,
        private CompartmentManager $compartmentManager,
        private ?ProductGroupingService $productGroupingService = null,
        private ?PlacementStrategyService $placementStrategyService = null
    ) {
    }

    /**
     * Pack items using compartment-based grid algorithm.
     *
     * @param array $items Array of items with: product_id, width, depth, height, quantity
     * @param float $roomWidth Room width
     * @param float $roomDepth Room depth
     * @param float $roomHeight Room height
     * @param array $options Options: grid config, column_max_height, etc.
     * @return array{placements: array, unplaced_items: array, utilization: float, compartments: array, grid: array}
     */
    public function pack(
        array $items,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight,
        array $options = []
    ): array {
        // Expand items by quantity
        $expandedItems = $this->expandItems($items);

        // Group items by product_id
        $itemsByProduct = $this->groupByProduct($expandedItems);

        // Calculate grid dimensions using smart grid calculator
        $numProducts = count($itemsByProduct);
        
        // Use smart grid calculation if items are provided
        if (!empty($items) && method_exists($this->compartmentManager, 'calculateOptimalGrid')) {
            try {
                $optimalGrid = $this->compartmentManager->calculateOptimalGrid(
                    $items,
                    $roomWidth,
                    $roomDepth,
                    $roomHeight,
                    array_merge($options, ['items' => $items])
                );
                $gridConfig = [
                    'columns' => $optimalGrid['columns'],
                    'rows' => $optimalGrid['rows'],
                    'cell_width' => $optimalGrid['cell_width'],
                    'cell_depth' => $optimalGrid['cell_depth'],
                ];
            } catch (\Exception $e) {
                // Fallback to simple grid calculation
                $gridConfig = $this->compartmentManager->calculateGrid(
                    $roomWidth,
                    $roomDepth,
                    $numProducts,
                    array_merge($options['grid'] ?? [], ['items' => $items, 'room_height' => $roomHeight])
                );
            }
        } else {
            $gridConfig = $this->compartmentManager->calculateGrid(
                $roomWidth,
                $roomDepth,
                $numProducts,
                array_merge($options['grid'] ?? [], ['items' => $items, 'room_height' => $roomHeight])
            );
        }

        $maxColumnHeight = $options['column_max_height'] ?? $roomHeight;
        $placements = [];
        $unplacedItems = [];
        $compartments = [];
        $columnHeights = []; // Track height per column

        // Initialize column heights
        for ($i = 0; $i < $gridConfig['columns']; $i++) {
            $columnHeights[$i] = 0.0;
        }

        $productIndex = 0;
        foreach ($itemsByProduct as $productId => $productItems) {
            // Calculate grid position for this compartment
            $row = (int) floor($productIndex / $gridConfig['columns']);
            $column = $productIndex % $gridConfig['columns'];

            // Get compartment boundary
            $compartmentBoundary = $this->compartmentManager->getCompartmentBoundary(
                $productId,
                $column,
                $row,
                $gridConfig['cell_width'],
                $gridConfig['cell_depth'],
                $roomWidth,
                $roomDepth
            );

            // Track compartment info
            $compartmentInfo = [
                'product_id' => $productId,
                'boundary' => $compartmentBoundary,
                'items_count' => 0,
                'placed_count' => 0,
            ];

            // Place items in this compartment
            // Use a grid-based approach within the compartment to avoid collisions
            
            // Get first item dimensions to calculate grid
            $firstItem = $productItems[0] ?? null;
            if (!$firstItem) {
                continue;
            }
            
            // Adaptive cell sizing: adjust based on product dimensions
            $gridCellWidth = $firstItem['width'];
            $gridCellDepth = $firstItem['depth'];
            
            // Allow cells to expand/contract within limits (10% margin)
            $minCellWidth = $gridCellWidth * 0.9;
            $maxCellWidth = min($compartmentBoundary['width'], $gridCellWidth * 1.5);
            $minCellDepth = $gridCellDepth * 0.9;
            $maxCellDepth = min($compartmentBoundary['depth'], $gridCellDepth * 1.5);
            
            // Optimize cell size for minimal waste
            $optimalCellWidth = min($maxCellWidth, max($minCellWidth, $gridCellWidth));
            $optimalCellDepth = min($maxCellDepth, max($minCellDepth, $gridCellDepth));
            
            $maxColumnsInCompartment = max(1, (int) floor($compartmentBoundary['width'] / $optimalCellWidth));
            $maxRowsInCompartment = max(1, (int) floor($compartmentBoundary['depth'] / $optimalCellDepth));
            
            // Track stack heights for each grid position
            $gridStacks = []; // ['col_row' => ['z' => float, 'height' => float]]

            foreach ($productItems as $item) {
                $itemWidth = $item['width'];
                $itemDepth = $item['depth'];
                $itemHeight = $item['height'];

                // Check if item fits in compartment
                if ($itemWidth > $compartmentBoundary['width'] || $itemDepth > $compartmentBoundary['depth']) {
                    $unplacedItems[] = [
                        'product_id' => $productId,
                        'width' => $itemWidth,
                        'depth' => $itemDepth,
                        'height' => $itemHeight,
                        'reason' => 'Item too large for compartment',
                    ];
                    continue;
                }

                // Check if item height fits in room
                if ($itemHeight > $roomHeight) {
                    $unplacedItems[] = [
                        'product_id' => $productId,
                        'width' => $itemWidth,
                        'depth' => $itemDepth,
                        'height' => $itemHeight,
                        'reason' => "Item height ({$itemHeight} cm) exceeds room height ({$roomHeight} cm)",
                    ];
                    continue;
                }

                // Try to place item in grid positions
                $placed = false;
                for ($colIdx = 0; $colIdx < $maxColumnsInCompartment && !$placed; $colIdx++) {
                    for ($rowIdx = 0; $rowIdx < $maxRowsInCompartment && !$placed; $rowIdx++) {
                        $gridKey = "{$colIdx}_{$rowIdx}";
                        
                        // Calculate position
                        $itemX = $compartmentBoundary['x'] + ($colIdx * $gridCellWidth);
                        $itemY = $compartmentBoundary['y'] + ($rowIdx * $gridCellDepth);
                        
                        // Ensure item fits strictly within compartment boundary (with small margin to prevent overlaps)
                        $compartmentRight = $compartmentBoundary['x'] + $compartmentBoundary['width'];
                        $compartmentBottom = $compartmentBoundary['y'] + $compartmentBoundary['depth'];
                        
                        // Use a small margin (0.1cm) to prevent floating-point precision issues
                        $margin = 0.1;
                        if ($itemX + $itemWidth > $compartmentRight - $margin ||
                            $itemY + $itemDepth > $compartmentBottom - $margin ||
                            $itemX < $compartmentBoundary['x'] + $margin ||
                            $itemY < $compartmentBoundary['y'] + $margin) {
                            continue;
                        }
                        
                        // Initialize stack if not exists
                        if (!isset($gridStacks[$gridKey])) {
                            $gridStacks[$gridKey] = ['z' => 0.0, 'height' => 0.0];
                        }
                        
                        $stack = &$gridStacks[$gridKey];
                        
                        // Check height limit
                        if ($stack['height'] + $itemHeight > $maxColumnHeight) {
                            continue; // Try next position
                        }
                        
                        $itemZ = $stack['z'];
                        
                        // Check for floor-level collision (only if placing on floor)
                        if ($itemZ < 0.1) {
                            // Check 2D rectangle overlap with all floor-level items
                            // Use precise collision detection
                            $hasCollision = false;
                            
                            // Create test box for collision detection
                            $testBox = new Box($itemX, $itemY, 0.0, $itemWidth, $itemDepth, $itemHeight);
                            
                            // Only check floor-level items for efficiency
                            foreach ($placements as $existingPlacement) {
                                // Skip non-floor items
                                if ($existingPlacement['z'] >= 0.1) {
                                    continue;
                                }
                                
                                $existingBox = new Box(
                                    $existingPlacement['x'],
                                    $existingPlacement['y'],
                                    0.0,
                                    $existingPlacement['width'],
                                    $existingPlacement['depth'],
                                    $existingPlacement['height']
                                );
                                
                                // Check if rectangles overlap
                                if ($this->collisionDetector->rectanglesOverlap2D($testBox, $existingBox)) {
                                    // Check if it's the exact same position (allow stacking)
                                    $sameX = abs($existingPlacement['x'] - $itemX) < 0.01;
                                    $sameY = abs($existingPlacement['y'] - $itemY) < 0.01;
                                    
                                    if ($sameX && $sameY) {
                                        // Same position - this is stacking, which is allowed
                                        // Continue to next placement check
                                        continue;
                                    }
                                    
                                    // Different position but overlapping - collision!
                                    $hasCollision = true;
                                    break;
                                }
                            }
                            
                            if ($hasCollision) {
                                continue; // Try next position
                            }
                        }

                        // Place the item
                        $placement = [
                            'product_id' => $productId,
                            'x' => round($itemX, 2),
                            'y' => round($itemY, 2),
                            'z' => round($itemZ, 2),
                            'width' => $itemWidth,
                            'depth' => $itemDepth,
                            'height' => $itemHeight,
                            'rotation' => '0',
                            'layer_index' => 0,
                            'stack_id' => null,
                            'stack_position' => 1,
                            'stack_base_x' => $itemX,
                            'stack_base_y' => $itemY,
                            'items_below_count' => 0,
                            'compartment_column' => $column,
                            'compartment_row' => $row,
                        ];

                        $placements[] = $placement;
                        $compartmentInfo['placed_count']++;
                        $compartmentInfo['items_count']++;

                        // Update stack position
                        $stack['z'] += $itemHeight;
                        $stack['height'] += $itemHeight;
                        $placed = true;
                    }
                }

                if (!$placed) {
                    $unplacedItems[] = [
                        'product_id' => $productId,
                        'width' => $itemWidth,
                        'depth' => $itemDepth,
                        'height' => $itemHeight,
                        'reason' => 'No space in compartment',
                    ];
                }
            }

            $compartments[] = $compartmentInfo;
            $productIndex++;
        }

        $utilization = $this->calculateUtilization($placements, $roomWidth, $roomDepth, $roomHeight);

        return [
            'placements' => $placements,
            'unplaced_items' => $unplacedItems,
            'utilization' => $utilization,
            'compartments' => $compartments,
            'grid' => $gridConfig,
        ];
    }

    /**
     * Expand items by quantity.
     *
     * @param array $items
     * @return array
     */
    private function expandItems(array $items): array
    {
        $expanded = [];

        foreach ($items as $item) {
            $quantity = $item['quantity'] ?? 1;

            for ($i = 0; $i < $quantity; $i++) {
                $expanded[] = [
                    'product_id' => $item['product_id'],
                    'width' => $item['width'],
                    'depth' => $item['depth'],
                    'height' => $item['height'],
                ];
            }
        }

        return $expanded;
    }

    /**
     * Group items by product_id.
     *
     * @param array $items
     * @return array<int, array>
     */
    private function groupByProduct(array $items): array
    {
        $grouped = [];

        foreach ($items as $item) {
            $productId = $item['product_id'];
            if (!isset($grouped[$productId])) {
                $grouped[$productId] = [];
            }
            $grouped[$productId][] = $item;
        }

        return $grouped;
    }

    /**
     * Calculate space utilization percentage.
     *
     * @param array $placements
     * @param float $roomWidth
     * @param float $roomDepth
     * @param float $roomHeight
     * @return float
     */
    private function calculateUtilization(
        array $placements,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight
    ): float {
        if (empty($placements)) {
            return 0.0;
        }

        $totalVolume = $roomWidth * $roomDepth * $roomHeight;
        $usedVolume = 0.0;

        foreach ($placements as $placement) {
            $usedVolume += $placement['width'] * $placement['depth'] * $placement['height'];
        }

        return ($usedVolume / $totalVolume) * 100;
    }
}

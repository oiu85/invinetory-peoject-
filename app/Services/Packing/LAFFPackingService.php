<?php

namespace App\Services\Packing;

use App\Algorithms\Spatial\Box;
use App\Algorithms\Spatial\Rectangle;
use App\Services\Packing\PackingServiceInterface;
use App\Services\Spatial\CollisionDetector;
use App\Services\Spatial\FreeSpaceManager;

class LAFFPackingService implements PackingServiceInterface
{
    public function __construct(
        private CollisionDetector $collisionDetector
    ) {
    }

    /**
     * Pack items using LAFF (Largest Area Fit First) algorithm.
     *
     * @param array $items Array of items with: product_id, width, depth, height, quantity, rotatable
     * @param float $roomWidth Room width
     * @param float $roomDepth Room depth
     * @param float $roomHeight Room height
     * @param array $options Options: allow_rotation, prefer_bottom, etc.
     * @return array{placements: array, unplaced_items: array, utilization: float}
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

        // Sort by base area (largest first) - LAFF algorithm
        usort($expandedItems, function ($a, $b) {
            $areaA = $a['width'] * $a['depth'];
            $areaB = $b['width'] * $b['depth'];

            if (abs($areaA - $areaB) < 0.01) {
                // Tiebreaker: taller items first
                return $b['height'] <=> $a['height'];
            }

            return $areaB <=> $areaA;
        });

        $freeSpaceManager = new FreeSpaceManager($roomWidth, $roomDepth, $roomHeight);
        $placements = [];
        $unplacedItems = [];
        $placedBoxes = [];
        
        // Track stacks by (product_id, stack_base_x, stack_base_y) for faster lookup
        $stackMap = [];

        foreach ($expandedItems as $item) {
            $placed = $this->placeItem(
                $item,
                $freeSpaceManager,
                $roomWidth,
                $roomDepth,
                $roomHeight,
                $placedBoxes,
                $placements, // Pass existing placements for stack checking
                $stackMap // Pass stack map for faster lookup
            );

            if ($placed) {
                $placements[] = $placed;
                
                // Only add to placedBoxes if it's floor-level (for collision detection)
                // Stacked items don't need to be in placedBoxes for floor collision
                if ($placed['z'] < 0.1) {
                    $placedBoxes[] = new Box(
                        $placed['x'],
                        $placed['y'],
                        $placed['z'],
                        $placed['width'],
                        $placed['depth'],
                        $placed['height']
                    );
                }
            } else {
                // Determine why item couldn't be placed
                $reason = 'No floor space available for this item';
                
                // Check if it's a height issue
                if ($item['height'] > $roomHeight) {
                    $reason = "Item height ({$item['height']} cm) exceeds room height ({$roomHeight} cm)";
                } else {
                    // Check if there's any free space that could fit this item
                    $freeRects = $freeSpaceManager->getFreeRectangles();
                    $hasSpace = false;
                    foreach ($freeRects as $rect) {
                        if ($item['width'] <= $rect->width && $item['depth'] <= $rect->depth) {
                            $hasSpace = true;
                            break;
                        }
                    }
                    
                    if (!$hasSpace) {
                        $reason = 'No free floor space large enough for this item';
                    } else {
                        // There is at least one candidate free rectangle, but none of them were usable
                        // once we applied boundary + collision checks.
                        $reason = 'No valid non-colliding floor position found for this item';
                    }
                }
                
                $unplacedItems[] = [
                    'product_id' => $item['product_id'],
                    'width' => $item['width'],
                    'depth' => $item['depth'],
                    'height' => $item['height'],
                    'reason' => $reason,
                ];
            }
        }

        $utilization = $this->calculateUtilization($placements, $roomWidth, $roomDepth, $roomHeight);

        return [
            'placements' => $placements,
            'unplaced_items' => $unplacedItems,
            'utilization' => $utilization,
        ];
    }


    /**
     * Expand items by quantity.
     *
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
     * Find existing stack for same product at given position using stack map.
     *
     * @param array $stackMap Stack map: key = "productId_x_y", value = stack info
     * @param int $productId Product ID to match
     * @param float $x X position
     * @param float $y Y position
     * @param float $tolerance Position tolerance in cm (default 1.0 for better matching)
     * @return array{stack_height: float, items_count: int, stack_id: int, stack_base_x: float, stack_base_y: float}|null
     */
    private function findExistingStackInMap(
        array &$stackMap,
        int $productId,
        float $x,
        float $y,
        float $tolerance = 1.0
    ): ?array {
        // Round positions to reduce floating point precision issues
        $roundedX = round($x, 1);
        $roundedY = round($y, 1);

        // Check all stacks for this product
        foreach ($stackMap as $key => $stackInfo) {
            if (!str_starts_with($key, "{$productId}_")) {
                continue;
            }

            $stackX = $stackInfo['stack_base_x'];
            $stackY = $stackInfo['stack_base_y'];

            // Check if position matches (within tolerance)
            if (abs($stackX - $roundedX) <= $tolerance && abs($stackY - $roundedY) <= $tolerance) {
                return $stackInfo;
            }
        }

        return null;
    }

    /**
     * Update stack map after placing an item.
     */
    private function updateStackMap(
        array &$stackMap,
        array $placement
    ): void {
        $productId = $placement['product_id'];
        $baseX = $placement['stack_base_x'];
        $baseY = $placement['stack_base_y'];
        $key = "{$productId}_{$baseX}_{$baseY}";

        if (!isset($stackMap[$key])) {
            $stackMap[$key] = [
                'stack_height' => 0.0,
                'items_count' => 0,
                'stack_id' => $placement['stack_id'],
                'stack_base_x' => $baseX,
                'stack_base_y' => $baseY,
            ];
        }

        $stackMap[$key]['stack_height'] += $placement['height'];
        $stackMap[$key]['items_count']++;
    }

    /**
     * Generate stack ID from product and position.
     */
    private function generateStackId(int $productId, float $x, float $y): int
    {
        return crc32("{$productId}_{$x}_{$y}");
    }

    /**
     * Get candidate floor rectangles that can fit the item, ordered by best-fit
     * (least waste) then bottom-left (lower Y, then lower X).
     *
     * @return array<Rectangle>
     */
    private function getCandidateFloorRects(
        FreeSpaceManager $freeSpaceManager,
        float $itemWidth,
        float $itemDepth
    ): array {
        $rects = array_values(array_filter(
            $freeSpaceManager->getFreeRectangles(),
            static fn (Rectangle $r) => $itemWidth <= $r->width && $itemDepth <= $r->depth
        ));

        usort($rects, static function (Rectangle $a, Rectangle $b) use ($itemWidth, $itemDepth) {
            $wasteA = $a->getArea() - ($itemWidth * $itemDepth);
            $wasteB = $b->getArea() - ($itemWidth * $itemDepth);

            if (abs($wasteA - $wasteB) > 0.01) {
                return $wasteA <=> $wasteB;
            }

            if (abs($a->y - $b->y) > 0.01) {
                return $a->y <=> $b->y;
            }

            return $a->x <=> $b->x;
        });

        return $rects;
    }

    /**
     * Place a single item with vertical stacking support.
     *
     * @return array|null Placement data or null if cannot place
     */
    private function placeItem(
        array $item,
        FreeSpaceManager $freeSpaceManager,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight,
        array $placedBoxes,
        array $placements = [],
        array &$stackMap = []
    ): ?array {
        // Check if item height fits in room
        if ($item['height'] > $roomHeight) {
            return null;
        }

        // PRIORITY 1: Try to stack on existing stack of same product
        // Check all existing stacks for this product to see if we can stack
        $bestStack = null;
        
        foreach ($stackMap as $key => $stackInfo) {
            if (!str_starts_with($key, "{$item['product_id']}_")) {
                continue;
            }

            $stackHeight = $stackInfo['stack_height'];
            $newStackHeight = $stackHeight + $item['height'];

            // Check if we can stack here (height fits in room)
            if ($newStackHeight <= $roomHeight) {
                // This is a valid stack position
                // Prefer lower stacks (more stable)
                if ($bestStack === null || $stackHeight < $bestStack['stack_height']) {
                    $bestStack = $stackInfo;
                }
            }
        }

        // If we found a stack, use it.
        // Note: If a stack is at/near the roof, it won't be chosen above because of the height check.
        // In that case we will place a NEW base on the floor (new position) for that product.
        if ($bestStack !== null) {
            $x = $bestStack['stack_base_x'];
            $y = $bestStack['stack_base_y'];
            $zPosition = $bestStack['stack_height'];
            $stackId = $bestStack['stack_id'];
            $stackPosition = $bestStack['items_count'] + 1;
            $stackBaseX = $bestStack['stack_base_x'];
            $stackBaseY = $bestStack['stack_base_y'];
            $itemsBelowCount = $bestStack['items_count'];
            $isStacked = true;
        } else {
            // PRIORITY 2: Find a new floor position.
            // IMPORTANT: try multiple candidate rectangles. findBestFit() returns only one rect and
            // can still lead to a collision if free rectangles overlap due to splitting.
            $candidates = $this->getCandidateFloorRects(
                $freeSpaceManager,
                $item['width'],
                $item['depth']
            );

            if (empty($candidates)) {
                return null;
            }

            $found = false;
            foreach ($candidates as $candidate) {
                $candidateX = round($candidate->x, 1);
                $candidateY = round($candidate->y, 1);

                $candidateBox = new Box(
                    $candidateX,
                    $candidateY,
                    0.0,
                    $item['width'],
                    $item['depth'],
                    $item['height']
                );

                // Verify boundaries
                if (! $this->collisionDetector->fitsInRoom2D($candidateBox, $roomWidth, $roomDepth)) {
                    continue;
                }

                // Check floor collision against already-placed floor boxes
                if ($this->collisionDetector->hasCollision2D($candidateBox, $placedBoxes)) {
                    continue;
                }

                // Found valid floor placement
                $x = $candidateX;
                $y = $candidateY;
                $zPosition = 0.0;
                $stackId = $this->generateStackId($item['product_id'], $x, $y);
                $stackPosition = 1;
                $stackBaseX = $x;
                $stackBaseY = $y;
                $itemsBelowCount = 0;
                $isStacked = false;
                $found = true;
                break;
            }

            if (! $found) {
                return null;
            }
        }

        // Round positions to reduce floating point issues
        $x = round($x, 1);
        $y = round($y, 1);
        $stackBaseX = round($stackBaseX, 1);
        $stackBaseY = round($stackBaseY, 1);

        $box = new Box(
            $x,
            $y,
            $zPosition,
            $item['width'],
            $item['depth'],
            $item['height']
        );

        // Verify boundaries
        if ($box->x < 0 || $box->y < 0 
            || $box->getRightX() > $roomWidth 
            || $box->getTopY() > $roomDepth) {
            return null;
        }

        // Check 2D collision only for floor-level items (stacked items don't need floor collision check)
        if (!$isStacked) {
            if ($this->collisionDetector->hasCollision2D($box, $placedBoxes)) {
                // This should be rare now because we try multiple candidates above.
                return null;
            }
        }

        // Create placement
        $placement = [
            'product_id' => $item['product_id'],
            'x' => $x,
            'y' => $y,
            'z' => $zPosition,
            'width' => $item['width'],
            'depth' => $item['depth'],
            'height' => $item['height'],
            'rotation' => '0', // Always no rotation
            'layer_index' => (int) floor($zPosition / 10), // Layer based on Z position
            'stack_id' => $stackId,
            'stack_position' => $stackPosition,
            'stack_base_x' => $stackBaseX,
            'stack_base_y' => $stackBaseY,
            'items_below_count' => $itemsBelowCount,
        ];

        // Update free space only if placing on floor (not stacking)
        if (!$isStacked) {
            $usedRect = new Rectangle(
                $placement['x'],
                $placement['y'],
                $placement['width'],
                $placement['depth'],
                $placement['height']
            );
            $freeSpaceManager->splitFreeSpace($usedRect);
        }

        // Update stack map
        $this->updateStackMap($stackMap, $placement);

        return $placement;
    }

    /**
     * Calculate space utilization percentage.
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

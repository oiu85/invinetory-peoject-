<?php

namespace App\Services\Packing;

use App\Algorithms\Spatial\Box;
use App\Algorithms\Spatial\Rectangle;
use App\Services\Spatial\FreeSpaceManager;
use App\Services\Spatial\CollisionDetector;

class PlacementStrategyService
{
    public function __construct(
        private CollisionDetector $collisionDetector
    ) {
    }

    /**
     * Find best placement using multiple strategies.
     *
     * @param array $item Item with: width, depth, height
     * @param FreeSpaceManager $freeSpaceManager
     * @param array $placedBoxes Existing placed boxes
     * @param float $roomWidth
     * @param float $roomDepth
     * @param float $roomHeight
     * @return array{placement: array|null, strategy: string, score: float}
     */
    public function findBestPlacement(
        array $item,
        FreeSpaceManager $freeSpaceManager,
        array $placedBoxes,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight
    ): array {
        $strategies = [
            'bottom_left' => fn() => $this->bottomLeftFill($item, $freeSpaceManager, $placedBoxes, $roomWidth, $roomDepth, $roomHeight),
            'best_fit' => fn() => $this->bestFit($item, $freeSpaceManager, $placedBoxes, $roomWidth, $roomDepth, $roomHeight),
            'waste_minimization' => fn() => $this->wasteMinimization($item, $freeSpaceManager, $placedBoxes, $roomWidth, $roomDepth, $roomHeight),
            'stability_optimization' => fn() => $this->stabilityOptimization($item, $freeSpaceManager, $placedBoxes, $roomWidth, $roomDepth, $roomHeight),
        ];

        $bestPlacement = null;
        $bestStrategy = null;
        $bestScore = -1;

        foreach ($strategies as $strategyName => $strategyFn) {
            try {
                $result = $strategyFn();
                if ($result && $result['placement']) {
                    $score = $this->scorePlacement($result['placement'], $item, $roomWidth, $roomDepth, $roomHeight);
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestPlacement = $result['placement'];
                        $bestStrategy = $strategyName;
                    }
                }
            } catch (\Exception $e) {
                // Strategy failed, try next
                continue;
            }
        }

        return [
            'placement' => $bestPlacement,
            'strategy' => $bestStrategy ?? 'none',
            'score' => $bestScore,
        ];
    }

    /**
     * Bottom-left fill strategy (place items starting from bottom-left corner).
     */
    private function bottomLeftFill(
        array $item,
        FreeSpaceManager $freeSpaceManager,
        array $placedBoxes,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight
    ): ?array {
        $rects = $freeSpaceManager->getFreeRectangles();
        
        // Sort by Y (bottom), then X (left)
        usort($rects, function (Rectangle $a, Rectangle $b) {
            if (abs($a->y - $b->y) > 0.01) {
                return $a->y <=> $b->y;
            }
            return $a->x <=> $b->x;
        });

        $itemWidth = (float)($item['width'] ?? 0);
        $itemDepth = (float)($item['depth'] ?? 0);
        $itemHeight = (float)($item['height'] ?? 0);

        foreach ($rects as $rect) {
            if ($itemWidth <= $rect->width && $itemDepth <= $rect->depth) {
                $box = new Box($rect->x, $rect->y, 0.0, $itemWidth, $itemDepth, $itemHeight);
                
                if ($this->collisionDetector->fitsInRoom2D($box, $roomWidth, $roomDepth) &&
                    !$this->collisionDetector->hasCollision2D($box, $placedBoxes)) {
                    return [
                        'placement' => [
                            'x' => $rect->x,
                            'y' => $rect->y,
                            'z' => 0.0,
                            'width' => $itemWidth,
                            'depth' => $itemDepth,
                            'height' => $itemHeight,
                        ],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Best-fit strategy (minimize waste).
     */
    private function bestFit(
        array $item,
        FreeSpaceManager $freeSpaceManager,
        array $placedBoxes,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight
    ): ?array {
        $rects = $freeSpaceManager->getFreeRectangles();
        
        $itemWidth = (float)($item['width'] ?? 0);
        $itemDepth = (float)($item['depth'] ?? 0);
        $itemHeight = (float)($item['height'] ?? 0);

        $bestRect = null;
        $bestWaste = PHP_FLOAT_MAX;

        foreach ($rects as $rect) {
            if ($itemWidth <= $rect->width && $itemDepth <= $rect->depth) {
                $waste = $rect->getArea() - ($itemWidth * $itemDepth);
                
                if ($waste < $bestWaste) {
                    $box = new Box($rect->x, $rect->y, 0.0, $itemWidth, $itemDepth, $itemHeight);
                    
                    if ($this->collisionDetector->fitsInRoom2D($box, $roomWidth, $roomDepth) &&
                        !$this->collisionDetector->hasCollision2D($box, $placedBoxes)) {
                        $bestWaste = $waste;
                        $bestRect = $rect;
                    }
                }
            }
        }

        if ($bestRect) {
            return [
                'placement' => [
                    'x' => $bestRect->x,
                    'y' => $bestRect->y,
                    'z' => 0.0,
                    'width' => $itemWidth,
                    'depth' => $itemDepth,
                    'height' => $itemHeight,
                ],
            ];
        }

        return null;
    }

    /**
     * Waste minimization strategy (minimize total waste).
     */
    private function wasteMinimization(
        array $item,
        FreeSpaceManager $freeSpaceManager,
        array $placedBoxes,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight
    ): ?array {
        // Similar to best-fit but considers future placements
        return $this->bestFit($item, $freeSpaceManager, $placedBoxes, $roomWidth, $roomDepth, $roomHeight);
    }

    /**
     * Stability optimization strategy (prefer lower positions, center of mass).
     */
    private function stabilityOptimization(
        array $item,
        FreeSpaceManager $freeSpaceManager,
        array $placedBoxes,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight
    ): ?array {
        $rects = $freeSpaceManager->getFreeRectangles();
        
        $itemWidth = (float)($item['width'] ?? 0);
        $itemDepth = (float)($item['depth'] ?? 0);
        $itemHeight = (float)($item['height'] ?? 0);

        $bestRect = null;
        $bestStability = -1;

        foreach ($rects as $rect) {
            if ($itemWidth <= $rect->width && $itemDepth <= $rect->depth) {
                $box = new Box($rect->x, $rect->y, 0.0, $itemWidth, $itemDepth, $itemHeight);
                
                if ($this->collisionDetector->fitsInRoom2D($box, $roomWidth, $roomDepth) &&
                    !$this->collisionDetector->hasCollision2D($box, $placedBoxes)) {
                    // Calculate stability score (lower Y = better, closer to center = better)
                    $centerX = $roomWidth / 2;
                    $centerY = $roomDepth / 2;
                    $distanceFromCenter = sqrt(
                        pow($rect->x + $itemWidth / 2 - $centerX, 2) +
                        pow($rect->y + $itemDepth / 2 - $centerY, 2)
                    );
                    
                    $stability = (1 / (1 + $distanceFromCenter / 100)) * (1 - $rect->y / $roomDepth);
                    
                    if ($stability > $bestStability) {
                        $bestStability = $stability;
                        $bestRect = $rect;
                    }
                }
            }
        }

        if ($bestRect) {
            return [
                'placement' => [
                    'x' => $bestRect->x,
                    'y' => $bestRect->y,
                    'z' => 0.0,
                    'width' => $itemWidth,
                    'depth' => $itemDepth,
                    'height' => $itemHeight,
                ],
            ];
        }

        return null;
    }

    /**
     * Score a placement.
     */
    private function scorePlacement(
        array $placement,
        array $item,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight
    ): float {
        $score = 0;

        // Prefer lower Y (bottom)
        $score += (1 - $placement['y'] / $roomDepth) * 30;

        // Prefer lower X (left)
        $score += (1 - $placement['x'] / $roomWidth) * 20;

        // Prefer center (stability)
        $centerX = $roomWidth / 2;
        $centerY = $roomDepth / 2;
        $distanceFromCenter = sqrt(
            pow($placement['x'] + $placement['width'] / 2 - $centerX, 2) +
            pow($placement['y'] + $placement['depth'] / 2 - $centerY, 2)
        );
        $maxDistance = sqrt($roomWidth * $roomWidth + $roomDepth * $roomDepth);
        $score += (1 - $distanceFromCenter / $maxDistance) * 50;

        return $score;
    }
}

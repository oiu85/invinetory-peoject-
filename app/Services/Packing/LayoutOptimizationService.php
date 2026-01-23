<?php

namespace App\Services\Packing;

use App\Algorithms\Spatial\Box;
use App\Services\Spatial\CollisionDetector;

class LayoutOptimizationService
{
    public function __construct(
        private CollisionDetector $collisionDetector
    ) {
    }

    /**
     * Optimize a layout by filling gaps and rearranging placements.
     *
     * @param array $placements Array of placements
     * @param float $roomWidth
     * @param float $roomDepth
     * @param float $roomHeight
     * @return array{placements: array, improvements: array, utilization_before: float, utilization_after: float}
     */
    public function optimizeLayout(
        array $placements,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight
    ): array {
        $utilizationBefore = $this->calculateUtilization($placements, $roomWidth, $roomDepth, $roomHeight);
        $improvements = [];

        // Step 1: Fill gaps
        $placements = $this->fillGaps($placements, $roomWidth, $roomDepth, $roomHeight, $improvements);

        // Step 2: Rearrange for better utilization
        $placements = $this->rearrangePlacements($placements, $roomWidth, $roomDepth, $roomHeight, $improvements);

        // Step 3: Compact placements
        $placements = $this->compactPlacements($placements, $roomWidth, $roomDepth, $roomHeight, $improvements);

        $utilizationAfter = $this->calculateUtilization($placements, $roomWidth, $roomDepth, $roomHeight);

        return [
            'placements' => $placements,
            'improvements' => $improvements,
            'utilization_before' => $utilizationBefore,
            'utilization_after' => $utilizationAfter,
            'improvement' => $utilizationAfter - $utilizationBefore,
        ];
    }

    /**
     * Fill gaps in the layout.
     */
    private function fillGaps(
        array $placements,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight,
        array &$improvements
    ): array {
        // Identify gaps (empty spaces between placements)
        $gaps = $this->identifyGaps($placements, $roomWidth, $roomDepth, $roomHeight);

        if (empty($gaps)) {
            return $placements;
        }

        // Try to move smaller items into gaps
        $sortedPlacements = $placements;
        usort($sortedPlacements, function ($a, $b) {
            $areaA = ($a['width'] ?? 0) * ($a['depth'] ?? 0);
            $areaB = ($b['width'] ?? 0) * ($b['depth'] ?? 0);
            return $areaA <=> $areaB; // Smallest first
        });

        $moved = 0;
        foreach ($sortedPlacements as $idx => $placement) {
            foreach ($gaps as $gapIdx => $gap) {
                if ($this->canFitInGap($placement, $gap)) {
                    // Move placement to gap
                    $placements[array_search($placement, $placements)] = [
                        ...$placement,
                        'x' => $gap['x'],
                        'y' => $gap['y'],
                    ];
                    unset($gaps[$gapIdx]);
                    $moved++;
                    break;
                }
            }
        }

        if ($moved > 0) {
            $improvements[] = "Moved {$moved} items to fill gaps";
        }

        return $placements;
    }

    /**
     * Rearrange placements for better utilization.
     */
    private function rearrangePlacements(
        array $placements,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight,
        array &$improvements
    ): array {
        // Sort by size (largest first) and try to place more efficiently
        $sorted = $placements;
        usort($sorted, function ($a, $b) {
            $areaA = ($a['width'] ?? 0) * ($a['depth'] ?? 0);
            $areaB = ($b['width'] ?? 0) * ($b['depth'] ?? 0);
            return $areaB <=> $areaA; // Largest first
        });

        $rearranged = [];
        $placedBoxes = [];

        foreach ($sorted as $placement) {
            // Try to place at bottom-left
            $x = 0;
            $y = 0;
            $placed = false;

            while (!$placed && $y < $roomDepth) {
                while (!$placed && $x < $roomWidth) {
                    $box = new Box(
                        $x,
                        $y,
                        $placement['z'] ?? 0,
                        $placement['width'],
                        $placement['depth'],
                        $placement['height']
                    );

                    if ($this->collisionDetector->fitsInRoom2D($box, $roomWidth, $roomDepth) &&
                        !$this->collisionDetector->hasCollision2D($box, $placedBoxes)) {
                        $rearranged[] = [
                            ...$placement,
                            'x' => $x,
                            'y' => $y,
                        ];
                        if ($placement['z'] < 0.1) {
                            $placedBoxes[] = $box;
                        }
                        $placed = true;
                    } else {
                        $x += 1; // Try next position
                    }
                }
                if (!$placed) {
                    $x = 0;
                    $y += 1;
                }
            }

            if (!$placed) {
                // Keep original placement if can't rearrange
                $rearranged[] = $placement;
            }
        }

        if (count($rearranged) !== count($placements)) {
            $improvements[] = 'Rearranged placements for better utilization';
        }

        return $rearranged;
    }

    /**
     * Compact placements (move items closer together).
     */
    private function compactPlacements(
        array $placements,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight,
        array &$improvements
    ): array {
        $compacted = [];
        $placedBoxes = [];

        // Sort by Y, then X (bottom-left order)
        $sorted = $placements;
        usort($sorted, function ($a, $b) {
            if (abs(($a['y'] ?? 0) - ($b['y'] ?? 0)) > 0.01) {
                return ($a['y'] ?? 0) <=> ($b['y'] ?? 0);
            }
            return ($a['x'] ?? 0) <=> ($b['x'] ?? 0);
        });

        foreach ($sorted as $placement) {
            // Try to move as far left and down as possible
            $x = $placement['x'] ?? 0;
            $y = $placement['y'] ?? 0;

            // Try moving left
            while ($x > 0) {
                $testX = $x - 1;
                $box = new Box(
                    $testX,
                    $y,
                    $placement['z'] ?? 0,
                    $placement['width'],
                    $placement['depth'],
                    $placement['height']
                );

                if ($this->collisionDetector->fitsInRoom2D($box, $roomWidth, $roomDepth) &&
                    !$this->collisionDetector->hasCollision2D($box, $placedBoxes)) {
                    $x = $testX;
                } else {
                    break;
                }
            }

            // Try moving down
            while ($y > 0) {
                $testY = $y - 1;
                $box = new Box(
                    $x,
                    $testY,
                    $placement['z'] ?? 0,
                    $placement['width'],
                    $placement['depth'],
                    $placement['height']
                );

                if ($this->collisionDetector->fitsInRoom2D($box, $roomWidth, $roomDepth) &&
                    !$this->collisionDetector->hasCollision2D($box, $placedBoxes)) {
                    $y = $testY;
                } else {
                    break;
                }
            }

            $compacted[] = [
                ...$placement,
                'x' => $x,
                'y' => $y,
            ];

            if (($placement['z'] ?? 0) < 0.1) {
                $placedBoxes[] = new Box($x, $y, 0, $placement['width'], $placement['depth'], $placement['height']);
            }
        }

        $improvements[] = 'Compacted placements to reduce gaps';

        return $compacted;
    }

    /**
     * Identify gaps in the layout.
     */
    private function identifyGaps(
        array $placements,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight
    ): array {
        // Simple gap detection: find empty rectangular areas
        $gaps = [];
        $gridSize = 10; // 10cm grid for gap detection

        for ($x = 0; $x < $roomWidth; $x += $gridSize) {
            for ($y = 0; $y < $roomDepth; $y += $gridSize) {
                $gapWidth = min($gridSize, $roomWidth - $x);
                $gapDepth = min($gridSize, $roomDepth - $y);

                // Check if this area is empty
                $isEmpty = true;
                foreach ($placements as $placement) {
                    $px = $placement['x'] ?? 0;
                    $py = $placement['y'] ?? 0;
                    $pWidth = $placement['width'] ?? 0;
                    $pDepth = $placement['depth'] ?? 0;

                    if (!($px + $pWidth <= $x || $px >= $x + $gapWidth ||
                          $py + $pDepth <= $y || $py >= $y + $gapDepth)) {
                        $isEmpty = false;
                        break;
                    }
                }

                if ($isEmpty && $gapWidth > 5 && $gapDepth > 5) { // Minimum gap size
                    $gaps[] = [
                        'x' => $x,
                        'y' => $y,
                        'width' => $gapWidth,
                        'depth' => $gapDepth,
                    ];
                }
            }
        }

        return $gaps;
    }

    /**
     * Check if a placement can fit in a gap.
     */
    private function canFitInGap(array $placement, array $gap): bool
    {
        $width = $placement['width'] ?? 0;
        $depth = $placement['depth'] ?? 0;

        return $width <= $gap['width'] && $depth <= $gap['depth'];
    }

    /**
     * Calculate utilization percentage.
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
            $usedVolume += ($placement['width'] ?? 0) * ($placement['depth'] ?? 0) * ($placement['height'] ?? 0);
        }

        return ($usedVolume / $totalVolume) * 100;
    }
}

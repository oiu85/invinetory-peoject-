<?php

namespace App\Services\Spatial;

use App\Algorithms\Spatial\Rectangle;

class FreeSpaceManager
{
    /**
     * @var array<Rectangle>
     */
    private array $freeRectangles = [];

    public function __construct(
        private float $roomWidth,
        private float $roomDepth,
        private float $roomHeight
    ) {
        // Initialize with one free rectangle representing the entire room
        $this->freeRectangles[] = new Rectangle(
            0,
            0,
            $this->roomWidth,
            $this->roomDepth,
            $this->roomHeight
        );
    }

    /**
     * Find the best fit rectangle for an item (2D floor placement only).
     * Height is ignored for placement logic - only width and depth matter.
     *
     * @param float $itemWidth Item width
     * @param float $itemDepth Item depth
     * @param float $itemHeight Item height (stored but not used for placement)
     * @return Rectangle|null Best fit rectangle or null if no fit
     */
    public function findBestFit(
        float $itemWidth,
        float $itemDepth,
        float $itemHeight
    ): ?Rectangle {
        $bestFit = null;
        $bestWaste = PHP_FLOAT_MAX;

        foreach ($this->freeRectangles as $rect) {
            // Only check width and depth (2D floor space), ignore height
            if ($itemWidth <= $rect->width && $itemDepth <= $rect->depth) {
                $waste = $rect->getArea() - ($itemWidth * $itemDepth);

                // Prefer bottom-left placement (lower Y, then lower X)
                if ($waste < $bestWaste
                    || ($waste == $bestWaste && $this->isBetterPosition($rect, $bestFit))) {
                    $bestWaste = $waste;
                    $bestFit = $rect;
                }
            }
        }

        return $bestFit;
    }

    /**
     * Check if rect1 is a better position than rect2 (bottom-left preference).
     */
    private function isBetterPosition(?Rectangle $rect1, ?Rectangle $rect2): bool
    {
        if ($rect2 === null) {
            return true;
        }

        if ($rect1 === null) {
            return false;
        }

        // Prefer lower Y, then lower X
        if (abs($rect1->y - $rect2->y) > 0.01) {
            return $rect1->y < $rect2->y;
        }

        return $rect1->x < $rect2->x;
    }

    /**
     * Split free rectangles after placing an item.
     *
     * @param Rectangle $usedRect Rectangle that was used for placement
     */
    public function splitFreeSpace(Rectangle $usedRect): void
    {
        $newRects = [];

        foreach ($this->freeRectangles as $rect) {
            if (! $rect->intersects($usedRect)) {
                $newRects[] = $rect;
                continue;
            }

            // Generate split rectangles
            $splits = $this->generateSplits($rect, $usedRect);
            $newRects = array_merge($newRects, $splits);
        }

        // Remove redundant rectangles
        $newRects = $this->removeRedundant($newRects);
        
        // Merge small rectangles to optimize space management
        $this->freeRectangles = $this->mergeSmallRectangles($newRects);
    }

    /**
     * Generate split rectangles when an item is placed.
     *
     * @return array<Rectangle>
     */
    private function generateSplits(Rectangle $freeRect, Rectangle $usedRect): array
    {
        $splits = [];

        // Right rectangle
        if ($usedRect->getRightX() < $freeRect->getRightX()) {
            $splits[] = new Rectangle(
                $usedRect->getRightX(),
                $freeRect->y,
                $freeRect->getRightX() - $usedRect->getRightX(),
                $freeRect->depth,
                $freeRect->height
            );
        }

        // Top rectangle
        if ($usedRect->getTopY() < $freeRect->getTopY()) {
            $splits[] = new Rectangle(
                $freeRect->x,
                $usedRect->getTopY(),
                $freeRect->width,
                $freeRect->getTopY() - $usedRect->getTopY(),
                $freeRect->height
            );
        }

        // Left rectangle
        if ($freeRect->x < $usedRect->x) {
            $splits[] = new Rectangle(
                $freeRect->x,
                $freeRect->y,
                $usedRect->x - $freeRect->x,
                $freeRect->depth,
                $freeRect->height
            );
        }

        // Bottom rectangle
        if ($freeRect->y < $usedRect->y) {
            $splits[] = new Rectangle(
                $freeRect->x,
                $freeRect->y,
                $freeRect->width,
                $usedRect->y - $freeRect->y,
                $freeRect->height
            );
        }

        return array_filter($splits, function (Rectangle $split) {
            return $split->width > 0 && $split->depth > 0;
        });
    }

    /**
     * Remove redundant rectangles (rectangles contained in others).
     *
     * @param array<Rectangle> $rects
     * @return array<Rectangle>
     */
    private function removeRedundant(array $rects): array
    {
        $filtered = [];

        foreach ($rects as $rect) {
            $isRedundant = false;

            foreach ($rects as $other) {
                if ($rect !== $other && $other->containsRectangle($rect)) {
                    $isRedundant = true;
                    break;
                }
            }

            if (! $isRedundant) {
                $filtered[] = $rect;
            }
        }

        return $filtered;
    }

    /**
     * Get all free rectangles.
     *
     * @return array<Rectangle>
     */
    public function getFreeRectangles(): array
    {
        return $this->freeRectangles;
    }

    /**
     * Merge small rectangles to optimize space management.
     *
     * @param array<Rectangle> $rects
     * @return array<Rectangle>
     */
    private function mergeSmallRectangles(array $rects): array
    {
        $minArea = 100; // Minimum area threshold (100 cm²)
        $merged = [];
        $processed = [];

        foreach ($rects as $i => $rect) {
            if (isset($processed[$i])) {
                continue;
            }

            // If rectangle is large enough, keep it
            if ($rect->getArea() >= $minArea) {
                $merged[] = $rect;
                $processed[$i] = true;
                continue;
            }

            // Try to merge with adjacent small rectangles
            $mergedRect = $rect;
            foreach ($rects as $j => $other) {
                if ($i === $j || isset($processed[$j])) {
                    continue;
                }

                // Check if rectangles are adjacent and can be merged
                if ($this->canMerge($mergedRect, $other)) {
                    $mergedRect = $this->mergeRectangles($mergedRect, $other);
                    $processed[$j] = true;
                }
            }

            $merged[] = $mergedRect;
            $processed[$i] = true;
        }

        return $merged;
    }

    /**
     * Check if two rectangles can be merged.
     */
    private function canMerge(Rectangle $a, Rectangle $b): bool
    {
        // Check if rectangles are adjacent (share an edge)
        $tolerance = 1.0; // 1cm tolerance

        // Check if same X and adjacent Y
        if (abs($a->x - $b->x) < $tolerance &&
            abs($a->width - $b->width) < $tolerance) {
            $aTop = $a->y + $a->depth;
            $bTop = $b->y + $b->depth;
            if (abs($aTop - $b->y) < $tolerance || abs($bTop - $a->y) < $tolerance) {
                return true;
            }
        }

        // Check if same Y and adjacent X
        if (abs($a->y - $b->y) < $tolerance &&
            abs($a->depth - $b->depth) < $tolerance) {
            $aRight = $a->x + $a->width;
            $bRight = $b->x + $b->width;
            if (abs($aRight - $b->x) < $tolerance || abs($bRight - $a->x) < $tolerance) {
                return true;
            }
        }

        return false;
    }

    /**
     * Merge two rectangles.
     */
    private function mergeRectangles(Rectangle $a, Rectangle $b): Rectangle
    {
        $minX = min($a->x, $b->x);
        $minY = min($a->y, $b->y);
        $maxX = max($a->x + $a->width, $b->x + $b->width);
        $maxY = max($a->y + $a->depth, $b->y + $b->depth);

        return new Rectangle(
            $minX,
            $minY,
            $maxX - $minX,
            $maxY - $minY,
            max($a->height, $b->height)
        );
    }

    /**
     * Get total free area.
     */
    public function getTotalFreeArea(): float
    {
        $total = 0.0;
        foreach ($this->freeRectangles as $rect) {
            $total += $rect->getArea();
        }

        return $total;
    }
}

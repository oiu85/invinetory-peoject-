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
     * Find the best fit rectangle for an item.
     *
     * @param float $itemWidth Item width
     * @param float $itemDepth Item depth
     * @param float $itemHeight Item height
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
            if ($rect->canFit(new Rectangle(0, 0, $itemWidth, $itemDepth, $itemHeight))) {
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
        $this->freeRectangles = $this->removeRedundant($newRects);
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

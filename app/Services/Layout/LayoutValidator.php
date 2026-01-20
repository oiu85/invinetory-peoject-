<?php

namespace App\Services\Layout;

use App\Algorithms\Spatial\Box;
use App\Services\Spatial\CollisionDetector;

class LayoutValidator
{
    public function __construct(
        private CollisionDetector $collisionDetector
    ) {
    }

    /**
     * Validate a complete layout.
     *
     * @param array $placements Array of placement data
     * @param float $roomWidth Room width
     * @param float $roomDepth Room depth
     * @param float $roomHeight Room height
     * @return array{valid: bool, errors: array, warnings: array}
     */
    public function validateLayout(
        array $placements,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight
    ): array {
        $errors = [];
        $warnings = [];
        $boxes = [];

        foreach ($placements as $index => $placement) {
            $box = new Box(
                $placement['x'],
                $placement['y'],
                $placement['z'],
                $placement['width'],
                $placement['depth'],
                $placement['height']
            );

            // Check boundaries
            if (! $this->collisionDetector->fitsInRoom($box, $roomWidth, $roomDepth, $roomHeight)) {
                $errors[] = "Placement #{$index} exceeds room boundaries";
            }

            // Check collisions
            if ($this->collisionDetector->hasCollision($box, $boxes)) {
                $errors[] = "Placement #{$index} collides with another item";
            }

            $boxes[] = $box;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Calculate space utilization.
     *
     * @param array $placements Array of placement data
     * @param float $roomWidth Room width
     * @param float $roomDepth Room depth
     * @param float $roomHeight Room height
     * @return float Utilization percentage (0-100)
     */
    public function calculateUtilization(
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
            $usedVolume += $placement['width']
                * $placement['depth']
                * $placement['height'];
        }

        return ($usedVolume / $totalVolume) * 100;
    }

    /**
     * Calculate floor area utilization.
     *
     * @param array $placements Array of placement data
     * @param float $roomWidth Room width
     * @param float $roomDepth Room depth
     * @return float Floor area utilization percentage (0-100)
     */
    public function calculateFloorUtilization(
        array $placements,
        float $roomWidth,
        float $roomDepth
    ): float {
        if (empty($placements)) {
            return 0.0;
        }

        $totalArea = $roomWidth * $roomDepth;
        $usedArea = 0.0;
        $coveredAreas = [];

        foreach ($placements as $placement) {
            $key = "{$placement['x']}_{$placement['y']}";
            if (! isset($coveredAreas[$key])) {
                $usedArea += $placement['width'] * $placement['depth'];
                $coveredAreas[$key] = true;
            }
        }

        return ($usedArea / $totalArea) * 100;
    }
}

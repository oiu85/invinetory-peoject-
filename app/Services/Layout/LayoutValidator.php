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

            // Check boundaries (2D for floor, height check separately)
            if (! $this->collisionDetector->fitsInRoom2D($box, $roomWidth, $roomDepth)) {
                $errors[] = "Placement #{$index} exceeds room floor boundaries";
            }
            
            // Check height separately
            if ($box->getTopZ() > $roomHeight) {
                $errors[] = "Placement #{$index} exceeds room height (top Z: {$box->getTopZ()}, room height: {$roomHeight})";
            }

            // Check collisions (2D only - but only for floor-level items)
            // Stacked items (z > 0) can share the same X,Y position
            $isFloorLevel = $placement['z'] < 0.1;
            
            if ($isFloorLevel) {
                // Only check collision with other floor-level items
                $floorBoxes = array_filter($boxes, function($b) {
                    return $b->z < 0.1;
                });
                
                if ($this->collisionDetector->hasCollision2D($box, $floorBoxes)) {
                    $errors[] = "Placement #{$index} collides with another item on floor";
                }
            } else {
                // For stacked items, verify they're actually stacked (same X,Y as a floor item)
                $hasBaseItem = false;
                foreach ($boxes as $existingBox) {
                    if ($existingBox->z < 0.1 
                        && abs($existingBox->x - $box->x) < 0.1 
                        && abs($existingBox->y - $box->y) < 0.1) {
                        $hasBaseItem = true;
                        break;
                    }
                }
                
                if (!$hasBaseItem) {
                    $errors[] = "Placement #{$index} is stacked (Z={$placement['z']}) but has no base item at X={$placement['x']}, Y={$placement['y']}";
                }
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

<?php

namespace App\Services\Spatial;

use App\Algorithms\Spatial\Box;

class CollisionDetector
{
    /**
     * Check if two boxes intersect (collide).
     */
    public function boxesIntersect(Box $box1, Box $box2): bool
    {
        return $box1->intersects($box2);
    }

    /**
     * Check if a box intersects with any box in a collection.
     *
     * @param Box $box Box to check
     * @param array<Box> $existingBoxes Existing boxes to check against
     * @return bool True if collision detected
     */
    public function hasCollision(Box $box, array $existingBoxes): bool
    {
        foreach ($existingBoxes as $existingBox) {
            if ($this->boxesIntersect($box, $existingBox)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a box fits within room boundaries.
     *
     * @param Box $box Box to check
     * @param float $roomWidth Room width
     * @param float $roomDepth Room depth
     * @param float $roomHeight Room height
     * @return bool True if box fits within room
     */
    public function fitsInRoom(
        Box $box,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight
    ): bool {
        return $box->x >= 0
            && $box->y >= 0
            && $box->z >= 0
            && $box->getRightX() <= $roomWidth
            && $box->getTopY() <= $roomDepth
            && $box->getTopZ() <= $roomHeight;
    }

    /**
     * Validate placement: check boundaries and collisions.
     *
     * @param Box $newBox New box to place
     * @param array<Box> $existingBoxes Existing boxes
     * @param float $roomWidth Room width
     * @param float $roomDepth Room depth
     * @param float $roomHeight Room height
     * @return array{valid: bool, reason: string|null}
     */
    public function validatePlacement(
        Box $newBox,
        array $existingBoxes,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight
    ): array {
        if (! $this->fitsInRoom($newBox, $roomWidth, $roomDepth, $roomHeight)) {
            return [
                'valid' => false,
                'reason' => 'Box exceeds room boundaries',
            ];
        }

        if ($this->hasCollision($newBox, $existingBoxes)) {
            return [
                'valid' => false,
                'reason' => 'Box collides with existing placement',
            ];
        }

        return [
            'valid' => true,
            'reason' => null,
        ];
    }
}

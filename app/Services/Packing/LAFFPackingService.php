<?php

namespace App\Services\Packing;

use App\Algorithms\Spatial\Box;
use App\Algorithms\Spatial\Rectangle;
use App\Services\Packing\PackingServiceInterface;
use App\Services\Spatial\CollisionDetector;
use App\Services\Spatial\FreeSpaceManager;
use App\Services\Spatial\RotationHandler;

class LAFFPackingService implements PackingServiceInterface
{
    public function __construct(
        private RotationHandler $rotationHandler,
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
        $allowRotation = $options['allow_rotation'] ?? true;
        $preferBottom = $options['prefer_bottom'] ?? true;

        // Expand items by quantity
        $expandedItems = $this->expandItems($items);

        // Sort by base area (largest first)
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

        foreach ($expandedItems as $item) {
            $placed = $this->placeItem(
                $item,
                $freeSpaceManager,
                $roomWidth,
                $roomDepth,
                $roomHeight,
                $allowRotation,
                $placedBoxes
            );

            if ($placed) {
                $placements[] = $placed;
                $placedBoxes[] = new Box(
                    $placed['x'],
                    $placed['y'],
                    $placed['z'],
                    $placed['width'],
                    $placed['depth'],
                    $placed['height']
                );
            } else {
                $unplacedItems[] = $item;
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
                    'rotatable' => $item['rotatable'] ?? true,
                ];
            }
        }

        return $expanded;
    }

    /**
     * Place a single item.
     *
     * @return array|null Placement data or null if cannot place
     */
    private function placeItem(
        array $item,
        FreeSpaceManager $freeSpaceManager,
        float $roomWidth,
        float $roomDepth,
        float $roomHeight,
        bool $allowRotation,
        array $placedBoxes
    ): ?array {
        $rotations = $this->rotationHandler->getAllRotations(
            $item['width'],
            $item['depth'],
            $item['height'],
            $allowRotation && ($item['rotatable'] ?? true)
        );

        $bestPlacement = null;
        $bestWaste = PHP_FLOAT_MAX;

        foreach ($rotations as $rotation => $dimensions) {
            $fitRect = $freeSpaceManager->findBestFit(
                $dimensions['width'],
                $dimensions['depth'],
                $dimensions['height']
            );

            if ($fitRect === null) {
                continue;
            }

            // Try to place at Z=0 first (ground level)
            $zPosition = 0;

            $box = new Box(
                $fitRect->x,
                $fitRect->y,
                $zPosition,
                $dimensions['width'],
                $dimensions['depth'],
                $dimensions['height']
            );

            // Check collision
            if ($this->collisionDetector->hasCollision($box, $placedBoxes)) {
                continue;
            }

            // Check boundaries
            if (! $this->collisionDetector->fitsInRoom($box, $roomWidth, $roomDepth, $roomHeight)) {
                continue;
            }

            $waste = $fitRect->getArea() - ($dimensions['width'] * $dimensions['depth']);

            if ($waste < $bestWaste) {
                $bestWaste = $waste;
                $bestPlacement = [
                    'product_id' => $item['product_id'],
                    'x' => $fitRect->x,
                    'y' => $fitRect->y,
                    'z' => $zPosition,
                    'width' => $dimensions['width'],
                    'depth' => $dimensions['depth'],
                    'height' => $dimensions['height'],
                    'rotation' => $rotation,
                    'layer_index' => 0,
                ];
            }
        }

        if ($bestPlacement) {
            $usedRect = new Rectangle(
                $bestPlacement['x'],
                $bestPlacement['y'],
                $bestPlacement['width'],
                $bestPlacement['depth'],
                $bestPlacement['height']
            );
            $freeSpaceManager->splitFreeSpace($usedRect);
        }

        return $bestPlacement;
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

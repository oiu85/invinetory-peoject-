<?php

namespace App\Services\Spatial;

class RotationHandler
{
    public const ROTATION_0 = '0';
    public const ROTATION_90 = '90';
    public const ROTATION_180 = '180';
    public const ROTATION_270 = '270';

    public const ALL_ROTATIONS = [
        self::ROTATION_0,
        self::ROTATION_90,
        self::ROTATION_180,
        self::ROTATION_270,
    ];

    /**
     * Get rotated dimensions for a given rotation angle.
     *
     * @param float $width Original width
     * @param float $depth Original depth
     * @param float $height Original height (never changes)
     * @param string $rotation Rotation angle: '0', '90', '180', '270'
     * @return array{width: float, depth: float, height: float}
     */
    public function getRotatedDimensions(
        float $width,
        float $depth,
        float $height,
        string $rotation
    ): array {
        return match ($rotation) {
            self::ROTATION_0, self::ROTATION_180 => [
                'width' => $width,
                'depth' => $depth,
                'height' => $height,
            ],
            self::ROTATION_90, self::ROTATION_270 => [
                'width' => $depth,
                'depth' => $width,
                'height' => $height,
            ],
            default => [
                'width' => $width,
                'depth' => $depth,
                'height' => $height,
            ],
        };
    }

    /**
     * Get all possible rotations for an item.
     *
     * @param float $width Original width
     * @param float $depth Original depth
     * @param float $height Original height
     * @param bool $rotatable Whether item can be rotated
     * @return array<string, array{width: float, depth: float, height: float}>
     */
    public function getAllRotations(
        float $width,
        float $depth,
        float $height,
        bool $rotatable = true
    ): array {
        if (! $rotatable) {
            return [
                self::ROTATION_0 => $this->getRotatedDimensions($width, $depth, $height, self::ROTATION_0),
            ];
        }

        $rotations = [];
        foreach (self::ALL_ROTATIONS as $rotation) {
            $rotations[$rotation] = $this->getRotatedDimensions($width, $depth, $height, $rotation);
        }

        return $rotations;
    }

    /**
     * Check if rotation is valid.
     */
    public function isValidRotation(string $rotation): bool
    {
        return in_array($rotation, self::ALL_ROTATIONS, true);
    }

    /**
     * Get the best rotation for fitting in a space.
     *
     * @param float $itemWidth Item width
     * @param float $itemDepth Item depth
     * @param float $itemHeight Item height
     * @param float $spaceWidth Available space width
     * @param float $spaceDepth Available space depth
     * @param float $spaceHeight Available space height
     * @param bool $rotatable Whether item can be rotated
     * @return string|null Best rotation or null if doesn't fit
     */
    public function getBestRotation(
        float $itemWidth,
        float $itemDepth,
        float $itemHeight,
        float $spaceWidth,
        float $spaceDepth,
        float $spaceHeight,
        bool $rotatable = true
    ): ?string {
        if ($itemHeight > $spaceHeight) {
            return null;
        }

        $rotations = $this->getAllRotations($itemWidth, $itemDepth, $itemHeight, $rotatable);

        foreach ($rotations as $rotation => $dimensions) {
            if ($dimensions['width'] <= $spaceWidth
                && $dimensions['depth'] <= $spaceDepth
                && $dimensions['height'] <= $spaceHeight) {
                return $rotation;
            }
        }

        return null;
    }
}

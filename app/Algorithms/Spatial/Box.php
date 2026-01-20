<?php

namespace App\Algorithms\Spatial;

class Box
{
    public function __construct(
        public float $x,
        public float $y,
        public float $z,
        public float $width,
        public float $depth,
        public float $height
    ) {
    }

    public function getVolume(): float
    {
        return $this->width * $this->depth * $this->height;
    }

    public function getBaseArea(): float
    {
        return $this->width * $this->depth;
    }

    public function getTopZ(): float
    {
        return $this->z + $this->height;
    }

    public function getRightX(): float
    {
        return $this->x + $this->width;
    }

    public function getTopY(): float
    {
        return $this->y + $this->depth;
    }

    public function contains(Point $point): bool
    {
        return $point->x >= $this->x
            && $point->x <= $this->getRightX()
            && $point->y >= $this->y
            && $point->y <= $this->getTopY()
            && $point->z >= $this->z
            && $point->z <= $this->getTopZ();
    }

    public function intersects(Box $other): bool
    {
        return !($other->x > $this->getRightX()
            || $other->getRightX() < $this->x
            || $other->y > $this->getTopY()
            || $other->getTopY() < $this->y
            || $other->z > $this->getTopZ()
            || $other->getTopZ() < $this->z);
    }

    public function canFit(Box $item): bool
    {
        return $item->width <= $this->width
            && $item->depth <= $this->depth
            && $item->height <= $this->height;
    }

    public function toArray(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
            'z' => $this->z,
            'width' => $this->width,
            'depth' => $this->depth,
            'height' => $this->height,
        ];
    }
}

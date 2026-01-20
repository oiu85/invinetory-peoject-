<?php

namespace App\Algorithms\Spatial;

class Rectangle
{
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $depth,
        public float $height = 0.0
    ) {
    }

    public function getArea(): float
    {
        return $this->width * $this->depth;
    }

    public function getVolume(): float
    {
        return $this->width * $this->depth * $this->height;
    }

    public function contains(Point $point): bool
    {
        return $point->x >= $this->x
            && $point->x <= $this->x + $this->width
            && $point->y >= $this->y
            && $point->y <= $this->y + $this->depth;
    }

    public function containsRectangle(Rectangle $other): bool
    {
        return $other->x >= $this->x
            && $other->x + $other->width <= $this->x + $this->width
            && $other->y >= $this->y
            && $other->y + $other->depth <= $this->y + $this->depth;
    }

    public function intersects(Rectangle $other): bool
    {
        return !($other->x > $this->x + $this->width
            || $other->x + $other->width < $this->x
            || $other->y > $this->y + $this->depth
            || $other->y + $other->depth < $this->y);
    }

    public function canFit(Rectangle $item): bool
    {
        return $item->width <= $this->width
            && $item->depth <= $this->depth
            && $item->height <= $this->height;
    }

    public function getRightX(): float
    {
        return $this->x + $this->width;
    }

    public function getTopY(): float
    {
        return $this->y + $this->depth;
    }

    public function toArray(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
            'width' => $this->width,
            'depth' => $this->depth,
            'height' => $this->height,
        ];
    }
}

<?php

namespace App\Algorithms\Spatial;

class Point
{
    public function __construct(
        public float $x,
        public float $y,
        public float $z = 0.0
    ) {
    }

    public function distanceTo(Point $other): float
    {
        $dx = $this->x - $other->x;
        $dy = $this->y - $other->y;
        $dz = $this->z - $other->z;

        return sqrt($dx * $dx + $dy * $dy + $dz * $dz);
    }

    public function equals(Point $other): bool
    {
        return abs($this->x - $other->x) < 0.01
            && abs($this->y - $other->y) < 0.01
            && abs($this->z - $other->z) < 0.01;
    }

    public function toArray(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
            'z' => $this->z,
        ];
    }
}

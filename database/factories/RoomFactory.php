<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true) . ' Room',
            'description' => $this->faker->sentence(),
            'width' => $this->faker->numberBetween(500, 2000),
            'depth' => $this->faker->numberBetween(400, 1500),
            'height' => $this->faker->numberBetween(200, 500),
            'warehouse_id' => Warehouse::factory(),
            'status' => 'active',
            'max_weight' => $this->faker->numberBetween(5000, 20000),
        ];
    }
}

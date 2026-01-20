<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductDimension;
use App\Models\Room;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class RoomSimulationSeeder extends Seeder
{
    public function run(): void
    {
        // Create warehouse
        $warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'address' => '123 Warehouse Street, City',
        ]);

        // Create rooms
        $room1 = Room::create([
            'name' => 'Main Storage Room A',
            'description' => 'Primary storage room for general products',
            'width' => 1000.0, // cm
            'depth' => 800.0,  // cm
            'height' => 300.0, // cm
            'warehouse_id' => $warehouse->id,
            'status' => 'active',
            'max_weight' => 10000.0, // kg
        ]);

        $room2 = Room::create([
            'name' => 'Secondary Storage Room B',
            'description' => 'Secondary storage room',
            'width' => 800.0,
            'depth' => 600.0,
            'height' => 250.0,
            'warehouse_id' => $warehouse->id,
            'status' => 'active',
            'max_weight' => 8000.0,
        ]);

        // Add dimensions to existing products
        $products = Product::all();

        foreach ($products as $index => $product) {
            if (! $product->productDimension) {
                ProductDimension::create([
                    'product_id' => $product->id,
                    'width' => 50.0 + ($index * 10),   // Varying sizes
                    'depth' => 50.0 + ($index * 10),
                    'height' => 30.0 + ($index * 5),
                    'weight' => 5.0 + ($index * 2),
                    'rotatable' => true,
                    'fragile' => $index % 3 === 0, // Every 3rd product is fragile
                ]);
            }
        }

        $this->command->info('Room simulation data seeded successfully!');
        $this->command->info("Created {$warehouse->name} with 2 rooms");
        $this->command->info('Added dimensions to ' . $products->count() . ' products');
    }
}

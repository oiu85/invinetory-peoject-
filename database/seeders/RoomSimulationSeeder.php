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

        // Add dimensions to existing products with realistic plastic carton sizes
        $products = Product::all();

        // Small plastic carton/bag size categories (reduced sizes)
        $sizeCategories = [
            ['width' => 12.0, 'depth' => 10.0, 'height' => 8.0, 'weight' => 0.2], // Small
            ['width' => 18.0, 'depth' => 15.0, 'height' => 12.0, 'weight' => 0.4], // Medium
            ['width' => 25.0, 'depth' => 20.0, 'height' => 15.0, 'weight' => 0.6], // Large
            ['width' => 30.0, 'depth' => 25.0, 'height' => 20.0, 'weight' => 0.8], // Extra Large
        ];

        foreach ($products as $index => $product) {
            if (! $product->productDimension) {
                // Cycle through size categories for variety
                $sizeCategory = $sizeCategories[$index % count($sizeCategories)];
                
                // Add small random variations (±2cm) for realism
                $width = $sizeCategory['width'] + (($index % 5) - 2) * 1.0;
                $depth = $sizeCategory['depth'] + (($index % 7) - 3) * 1.0;
                $height = $sizeCategory['height'] + (($index % 3) - 1) * 1.0;
                
                // Ensure dimensions stay within small range (8-35cm)
                $width = max(8.0, min(35.0, $width));
                $depth = max(8.0, min(35.0, $depth));
                $height = max(5.0, min(25.0, $height));
                
                ProductDimension::create([
                    'product_id' => $product->id,
                    'width' => round($width, 1),
                    'depth' => round($depth, 1),
                    'height' => round($height, 1),
                    'weight' => $sizeCategory['weight'] + (($index % 3) * 0.3),
                    'rotatable' => true,
                    'fragile' => $index % 4 === 0, // Every 4th product is fragile
                ]);
            }
        }

        $this->command->info('Room simulation data seeded successfully!');
        $this->command->info("Created {$warehouse->name} with 2 rooms");
        $this->command->info('Added dimensions to ' . $products->count() . ' products');
    }
}

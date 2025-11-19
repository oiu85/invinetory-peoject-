<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\WarehouseStock;
use App\Models\DriverStock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call the FakerDataSeeder
        $this->call([
            FakerDataSeeder::class,
        ]);
    }
    
    // Old seeder code kept for reference - uncomment if needed
    /*
    public function runOld(): void
    {
        // Create Admin User
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@inventory.com',
            'password' => Hash::make('password'),
            'type' => 'admin',
        ]);

        // Create Test Driver
        $driver = User::create([
            'name' => 'Driver One',
            'email' => 'driver@inventory.com',
            'password' => Hash::make('password'),
            'type' => 'driver',
        ]);

        // Create Categories
        $category1 = Category::create([
            'name' => 'Electronics',
            'description' => 'Electronic products and gadgets',
        ]);

        $category2 = Category::create([
            'name' => 'Food & Beverages',
            'description' => 'Food and drink items',
        ]);

        $category3 = Category::create([
            'name' => 'Clothing',
            'description' => 'Clothing and apparel',
        ]);

        // Create Products
        $products = [
            [
                'name' => 'Laptop',
                'price' => 999.99,
                'category_id' => $category1->id,
                'description' => 'High-performance laptop',
            ],
            [
                'name' => 'Smartphone',
                'price' => 599.99,
                'category_id' => $category1->id,
                'description' => 'Latest smartphone model',
            ],
            [
                'name' => 'Bread',
                'price' => 2.50,
                'category_id' => $category2->id,
                'description' => 'Fresh bread loaf',
            ],
            [
                'name' => 'Water Bottle',
                'price' => 1.00,
                'category_id' => $category2->id,
                'description' => '500ml water bottle',
            ],
            [
                'name' => 'T-Shirt',
                'price' => 19.99,
                'category_id' => $category3->id,
                'description' => 'Cotton t-shirt',
            ],
            [
                'name' => 'Jeans',
                'price' => 49.99,
                'category_id' => $category3->id,
                'description' => 'Classic blue jeans',
            ],
        ];

        foreach ($products as $productData) {
            $product = Product::create($productData);
            
            // Create warehouse stock (random quantity between 50-200)
            WarehouseStock::create([
                'product_id' => $product->id,
                'quantity' => rand(50, 200),
            ]);
        }

        // Assign some stock to driver
        $driverProducts = Product::take(3)->get();
        foreach ($driverProducts as $product) {
            $quantity = rand(5, 20);
            
            // Decrease warehouse stock
            $warehouseStock = WarehouseStock::where('product_id', $product->id)->first();
            $warehouseStock->decrement('quantity', $quantity);
            
            // Create driver stock
            DriverStock::create([
                'driver_id' => $driver->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
            ]);
        }

        $this->command->info('Database seeded successfully!');
        $this->command->info('Admin: admin@inventory.com / password');
        $this->command->info('Driver: driver@inventory.com / password');
    }
    */
}

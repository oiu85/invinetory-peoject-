<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\DriverStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DriverStockTest extends TestCase
{
    use RefreshDatabase;

    private function createDriver(): User
    {
        return User::factory()->create([
            'type' => 'driver',
            'email' => 'driver@test.com',
            'password' => Hash::make('password'),
        ]);
    }

    private function getAuthToken(User $driver): string
    {
        $response = $this->postJson('/api/driver/login', [
            'email' => $driver->email,
            'password' => 'password',
        ]);

        return $response->json('token');
    }

    public function test_driver_can_view_their_stock(): void
    {
        $driver = $this->createDriver();
        $token = $this->getAuthToken($driver);

        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        DriverStock::factory()->create([
            'driver_id' => $driver->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/driver/my-stock');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'product_id', 'product_name', 'quantity', 'price'],
                ],
                'meta',
            ]);
    }

    public function test_driver_can_search_stock_by_product_name(): void
    {
        $driver = $this->createDriver();
        $token = $this->getAuthToken($driver);

        $category = Category::factory()->create();
        $product1 = Product::factory()->create(['name' => 'Product A', 'category_id' => $category->id]);
        $product2 = Product::factory()->create(['name' => 'Product B', 'category_id' => $category->id]);

        DriverStock::factory()->create([
            'driver_id' => $driver->id,
            'product_id' => $product1->id,
        ]);
        DriverStock::factory()->create([
            'driver_id' => $driver->id,
            'product_id' => $product2->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/driver/my-stock?search=Product A');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_driver_can_view_stock_statistics(): void
    {
        $driver = $this->createDriver();
        $token = $this->getAuthToken($driver);

        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'price' => 10.00]);
        DriverStock::factory()->create([
            'driver_id' => $driver->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/driver/my-stock/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_products',
                    'total_quantity',
                    'stock_value',
                    'low_stock_count',
                ],
            ]);
    }
}

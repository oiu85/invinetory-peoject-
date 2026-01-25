<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\DriverStock;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DriverDashboardTest extends TestCase
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

    public function test_driver_can_view_dashboard(): void
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

        Sale::factory()->create([
            'driver_id' => $driver->id,
            'total_amount' => 100.00,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/driver/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'quick_stats',
                    'recent_sales',
                    'low_stock_products',
                ],
            ]);
    }
}

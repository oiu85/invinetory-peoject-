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

class DriverSalesTest extends TestCase
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

    public function test_driver_can_view_their_sales(): void
    {
        $driver = $this->createDriver();
        $token = $this->getAuthToken($driver);

        Sale::factory()->create([
            'driver_id' => $driver->id,
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/sales');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'invoice_number', 'customer_name', 'total_amount'],
                ],
                'meta',
            ]);
    }

    public function test_driver_can_search_sales_by_customer_name(): void
    {
        $driver = $this->createDriver();
        $token = $this->getAuthToken($driver);

        Sale::factory()->create([
            'driver_id' => $driver->id,
            'customer_name' => 'John Doe',
        ]);
        Sale::factory()->create([
            'driver_id' => $driver->id,
            'customer_name' => 'Jane Smith',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/sales?search=John');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_driver_can_view_sales_statistics(): void
    {
        $driver = $this->createDriver();
        $token = $this->getAuthToken($driver);

        Sale::factory()->count(5)->create([
            'driver_id' => $driver->id,
            'total_amount' => 100.00,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/driver/sales/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'all_time',
                    'today',
                    'this_week',
                    'this_month',
                    'average_sale_amount',
                ],
            ]);
    }
}

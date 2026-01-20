<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoomManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['type' => 'admin']);
    }

    public function test_can_create_room(): void
    {
        Sanctum::actingAs($this->admin);

        $warehouse = Warehouse::factory()->create();

        $response = $this->postJson('/api/rooms', [
            'name' => 'Test Room',
            'width' => 1000,
            'depth' => 800,
            'height' => 300,
            'warehouse_id' => $warehouse->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'name',
            'width',
            'depth',
            'height',
        ]);
    }

    public function test_can_get_room_stats(): void
    {
        Sanctum::actingAs($this->admin);

        $room = Room::factory()->create();

        $response = $this->getJson("/api/rooms/{$room->id}/stats");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'room_id',
            'room_name',
            'dimensions',
        ]);
    }
}

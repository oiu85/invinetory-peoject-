<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\Room;
use App\Models\RoomStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(): JsonResponse
    {
        $rooms = Room::with('warehouse')->get();

        return response()->json($rooms);
    }

    public function store(StoreRoomRequest $request): JsonResponse
    {
        $room = Room::create($request->validated());

        return response()->json($room->load('warehouse'), 201);
    }

    public function show(int $id): JsonResponse
    {
        $room = Room::with(['warehouse', 'layouts'])->findOrFail($id);

        return response()->json($room);
    }

    public function update(UpdateRoomRequest $request, int $id): JsonResponse
    {
        $room = Room::findOrFail($id);
        $room->update($request->validated());

        return response()->json($room->load('warehouse'));
    }

    public function destroy(int $id): JsonResponse
    {
        $room = Room::findOrFail($id);
        $room->delete();

        return response()->json(['message' => 'Room deleted successfully']);
    }

    public function stats(int $id): JsonResponse
    {
        $room = Room::with(['layouts.placements'])->findOrFail($id);

        $latestLayout = $room->layouts()->latest()->first();

        $stats = [
            'room_id' => $room->id,
            'room_name' => $room->name,
            'dimensions' => [
                'width' => $room->width,
                'depth' => $room->depth,
                'height' => $room->height,
                'volume' => $room->volume,
                'floor_area' => $room->floor_area,
            ],
            'latest_layout' => $latestLayout ? [
                'layout_id' => $latestLayout->id,
                'utilization_percentage' => $latestLayout->utilization_percentage,
                'total_items_placed' => $latestLayout->total_items_placed,
                'total_items_attempted' => $latestLayout->total_items_attempted,
                'created_at' => $latestLayout->created_at,
            ] : null,
        ];

        return response()->json($stats);
    }

    /**
     * Get per-product stock quantities for a specific room.
     */
    public function stock(int $id): JsonResponse
    {
        $room = Room::findOrFail($id);

        $stocks = RoomStock::with('product.category')
            ->where('room_id', $room->id)
            ->orderBy('product_id')
            ->get()
            ->map(function (RoomStock $stock) {
                return [
                    'room_id' => $stock->room_id,
                    'product_id' => $stock->product_id,
                    'product_name' => $stock->product?->name,
                    'category' => $stock->product?->category,
                    'quantity' => $stock->quantity,
                ];
            });

        return response()->json([
            'room_id' => $room->id,
            'room_name' => $room->name,
            'stocks' => $stocks,
        ]);
    }

    /**
     * Get availability of a product across all rooms.
     */
    public function productRoomAvailability(int $id): JsonResponse
    {
        $stocks = RoomStock::with('room.warehouse')
            ->where('product_id', $id)
            ->where('quantity', '>', 0)
            ->orderBy('room_id')
            ->get()
            ->map(function (RoomStock $stock) {
                return [
                    'room_id' => $stock->room_id,
                    'room_name' => $stock->room?->name,
                    'warehouse_id' => $stock->room?->warehouse_id,
                    'warehouse_name' => $stock->room?->warehouse?->name,
                    'quantity' => $stock->quantity,
                ];
            });

        return response()->json([
            'product_id' => $id,
            'rooms' => $stocks,
        ]);
    }
}

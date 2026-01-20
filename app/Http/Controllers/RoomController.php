<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\Room;
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
}

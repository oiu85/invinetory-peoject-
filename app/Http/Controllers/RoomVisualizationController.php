<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\JsonResponse;

class RoomVisualizationController extends Controller
{
    public function index(int $id): JsonResponse
    {
        $room = Room::with(['layouts.placements.product'])->findOrFail($id);
        $latestLayout = $room->layouts()->latest()->first();

        if (! $latestLayout) {
            return response()->json([
                'room_id' => $room->id,
                'room_name' => $room->name,
                'layout' => null,
                'message' => 'No layout found for this room',
            ]);
        }

        $placements = $latestLayout->placements()->with('product')->get();

        return response()->json([
            'room_id' => $room->id,
            'room_name' => $room->name,
            'room_dimensions' => [
                'width' => $room->width,
                'depth' => $room->depth,
                'height' => $room->height,
            ],
            'layout' => [
                'layout_id' => $latestLayout->id,
                'algorithm_used' => $latestLayout->algorithm_used,
                'utilization_percentage' => $latestLayout->utilization_percentage,
                'total_items_placed' => $latestLayout->total_items_placed,
                'placements' => $placements->map(function ($placement) {
                    return [
                        'placement_id' => $placement->id,
                        'product_id' => $placement->product_id,
                        'product_name' => $placement->product->name ?? 'Unknown',
                        'x' => $placement->x_position,
                        'y' => $placement->y_position,
                        'z' => $placement->z_position,
                        'width' => $placement->width,
                        'depth' => $placement->depth,
                        'height' => $placement->height,
                        'rotation' => $placement->rotation,
                        'layer_index' => $placement->layer_index,
                        'stack_id' => $placement->stack_id,
                        'stack_position' => $placement->stack_position,
                    ];
                }),
            ],
        ]);
    }

    public function grid(int $id): JsonResponse
    {
        $room = Room::with(['layouts.placements'])->findOrFail($id);
        $latestLayout = $room->layouts()->latest()->first();

        if (! $latestLayout) {
            return response()->json(['grid' => []]);
        }

        $placements = $latestLayout->placements;

        $grid = [];
        foreach ($placements as $placement) {
            $grid[] = [
                'x' => $placement->x_position,
                'y' => $placement->y_position,
                'z' => $placement->z_position,
                'width' => $placement->width,
                'depth' => $placement->depth,
                'height' => $placement->height,
                'product_id' => $placement->product_id,
            ];
        }

        return response()->json([
            'room_width' => $room->width,
            'room_depth' => $room->depth,
            'room_height' => $room->height,
            'grid' => $grid,
        ]);
    }

    public function threeD(int $id): JsonResponse
    {
        $room = Room::with(['layouts.placements.product'])->findOrFail($id);
        $latestLayout = $room->layouts()->latest()->first();

        if (! $latestLayout) {
            return response()->json(['objects' => []]);
        }

        $placements = $latestLayout->placements()->with('product')->get();

        $objects = $placements->map(function ($placement) {
            return [
                'id' => $placement->id,
                'product_id' => $placement->product_id,
                'product_name' => $placement->product->name ?? 'Unknown',
                'position' => [
                    'x' => $placement->x_position,
                    'y' => $placement->y_position,
                    'z' => $placement->z_position,
                ],
                'dimensions' => [
                    'width' => $placement->width,
                    'depth' => $placement->depth,
                    'height' => $placement->height,
                ],
                'rotation' => (int) $placement->rotation,
                'layer_index' => $placement->layer_index,
                'stack_id' => $placement->stack_id,
                'stack_position' => $placement->stack_position,
            ];
        });

        return response()->json([
            'room' => [
                'width' => $room->width,
                'depth' => $room->depth,
                'height' => $room->height,
            ],
            'objects' => $objects,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\DriverStock;
use App\Models\WarehouseStock;
use App\Models\User;
use App\Models\Room;
use App\Models\RoomStock;
use App\Models\ItemPlacement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AssignStockController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'driver_id' => 'required|exists:users,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'room_id' => 'required|exists:rooms,id',
        ]);

        $driver = User::findOrFail($validated['driver_id']);
        if (! $driver->isDriver()) {
            return response()->json(['message' => 'User is not a driver'], 400);
        }

        $room = Room::findOrFail($validated['room_id']);

        // Check room stock for this product
        $roomStock = RoomStock::where('room_id', $room->id)
            ->where('product_id', $validated['product_id'])
            ->first();

        if (! $roomStock || $roomStock->quantity < $validated['quantity']) {
            $availableInRoom = $roomStock?->quantity ?? 0;

            return response()->json([
                'message' => 'Insufficient room stock for this product',
                'room_id' => $room->id,
                'available_in_room' => $availableInRoom,
            ], 400);
        }

        // Check overall warehouse stock as safety
        $warehouseStock = WarehouseStock::where('product_id', $validated['product_id'])->first();
        $availableWarehouse = $warehouseStock?->quantity ?? 0;
        if ($availableWarehouse < $validated['quantity']) {
            return response()->json([
                'message' => 'Insufficient warehouse stock',
                'available_in_warehouse' => $availableWarehouse,
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Decrease warehouse stock total
            $warehouseStock->decrement('quantity', $validated['quantity']);

            // Decrease room stock
            $roomStock->decrement('quantity', $validated['quantity']);

            // Increase driver stock
            $driverStock = DriverStock::updateOrCreate(
                [
                    'driver_id' => $validated['driver_id'],
                    'product_id' => $validated['product_id'],
                ],
                []
            );
            $driverStock->increment('quantity', $validated['quantity']);

            // Remove matching placements from this room (top-of-stack first)
            $toRemove = $validated['quantity'];

            $placementsQuery = ItemPlacement::whereHas('roomLayout', function ($query) use ($room) {
                    $query->where('room_id', $room->id);
                })
                ->where('product_id', $validated['product_id'])
                ->orderByDesc('z_position')      // highest Z first (top of stack)
                ->orderByDesc('stack_position') // then stack position
                ->orderByDesc('id');            // newest first

            /** @var ItemPlacement[] $placements */
            $placements = $placementsQuery->get();

            foreach ($placements as $placement) {
                if ($toRemove <= 0) {
                    break;
                }

                $placement->delete();
                $toRemove--;
            }

            if ($toRemove > 0) {
                // Not enough placements to match requested quantity; rollback to keep consistency
                DB::rollBack();

                return response()->json([
                    'message' => 'Not enough placed items in room to assign this quantity',
                    'requested' => $validated['quantity'],
                    'removed_from_room' => $validated['quantity'] - $toRemove,
                ], 400);
            }

            DB::commit();

            return response()->json([
                'message' => 'Stock assigned successfully',
                'driver_stock' => $driverStock->fresh(['product', 'driver']),
                'room_id' => $room->id,
                'removed_from_room' => $validated['quantity'],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to assign stock',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}


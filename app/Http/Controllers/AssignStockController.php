<?php

namespace App\Http\Controllers;

use App\Models\DriverStock;
use App\Models\WarehouseStock;
use App\Models\User;
use App\Models\Room;
use App\Models\RoomStock;
use App\Models\ItemPlacement;
use App\Models\RoomLayout;
use App\Models\StockAssignment;
use App\Models\Product;
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

        // Check warehouse stock first (primary source)
        $warehouseStock = WarehouseStock::where('product_id', $validated['product_id'])->first();
        $availableWarehouse = $warehouseStock?->quantity ?? 0;
        
        if ($availableWarehouse < $validated['quantity']) {
            return response()->json([
                'message' => 'Insufficient warehouse stock',
                'available_in_warehouse' => $availableWarehouse,
                'requested' => $validated['quantity'],
            ], 400);
        }

        // Check room stock for this product (optional - may not exist if assigning from warehouse)
        $roomStock = RoomStock::where('room_id', $room->id)
            ->where('product_id', $validated['product_id'])
            ->first();

        $availableInRoom = $roomStock?->quantity ?? 0;
        $assigningFromRoom = $roomStock && $roomStock->quantity > 0;
        
        // If room has stock, validate it's sufficient
        // If room has no stock (0 or null), we're assigning from warehouse, which is allowed
        if ($assigningFromRoom && $roomStock->quantity < $validated['quantity']) {
            return response()->json([
                'message' => 'Insufficient room stock for this product',
                'room_id' => $room->id,
                'available_in_room' => $availableInRoom,
                'requested' => $validated['quantity'],
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Always decrease warehouse stock (primary source)
            $warehouseStock->decrement('quantity', $validated['quantity']);

            // Decrease room stock only if it exists and has stock
            $removedFromRoom = 0;
            if ($assigningFromRoom && $roomStock) {
                $roomStock->decrement('quantity', $validated['quantity']);
                $removedFromRoom = $validated['quantity'];
            }

            // Get product price for tracking
            $product = Product::findOrFail($validated['product_id']);
            
            // Increase driver stock
            $driverStock = DriverStock::updateOrCreate(
                [
                    'driver_id' => $validated['driver_id'],
                    'product_id' => $validated['product_id'],
                ],
                []
            );
            $driverStock->increment('quantity', $validated['quantity']);

            // Log the assignment for history tracking
            StockAssignment::create([
                'driver_id' => $validated['driver_id'],
                'product_id' => $validated['product_id'],
                'room_id' => $validated['room_id'],
                'quantity' => $validated['quantity'],
                'assigned_from' => $assigningFromRoom ? 'room' : 'warehouse',
                'product_price_at_assignment' => $product->price,
            ]);

            // Remove matching placements from this room only if we're assigning from room
            // If assigning from warehouse (room has no stock), skip placement removal
            $layoutNeedsRefresh = false;
            if ($assigningFromRoom) {
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

                $layoutNeedsRefresh = true; // Layout needs refresh when items are removed from room
            }

            DB::commit();

            $latestLayout = $layoutNeedsRefresh 
                ? RoomLayout::where('room_id', $room->id)->latest()->first()
                : null;

            return response()->json([
                'message' => 'Stock assigned successfully',
                'driver_stock' => $driverStock->fresh(['product', 'driver']),
                'room_id' => $room->id,
                'assigned_from' => $assigningFromRoom ? 'room' : 'warehouse',
                'removed_from_room' => $removedFromRoom,
                'removed_from_warehouse' => $validated['quantity'],
                'layout_updated' => $layoutNeedsRefresh,
                'layout_id' => $latestLayout?->id,
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


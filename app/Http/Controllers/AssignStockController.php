<?php

namespace App\Http\Controllers;

use App\Models\DriverStock;
use App\Models\WarehouseStock;
use App\Models\User;
use Illuminate\Http\Request;

class AssignStockController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'driver_id' => 'required|exists:users,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $driver = User::findOrFail($validated['driver_id']);
        
        if (!$driver->isDriver()) {
            return response()->json(['message' => 'User is not a driver'], 400);
        }

        // Check warehouse stock
        $warehouseStock = WarehouseStock::where('product_id', $validated['product_id'])->first();
        
        if (!$warehouseStock || $warehouseStock->quantity < $validated['quantity']) {
            return response()->json([
                'message' => 'Insufficient warehouse stock'
            ], 400);
        }

        // Decrease warehouse stock
        $warehouseStock->decrement('quantity', $validated['quantity']);

        // Increase driver stock
        $driverStock = DriverStock::updateOrCreate(
            [
                'driver_id' => $validated['driver_id'],
                'product_id' => $validated['product_id'],
            ],
            []
        );

        $driverStock->increment('quantity', $validated['quantity']);

        return response()->json([
            'message' => 'Stock assigned successfully',
            'driver_stock' => $driverStock->fresh(['product', 'driver']),
        ], 201);
    }
}


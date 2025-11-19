<?php

namespace App\Http\Controllers;

use App\Models\DriverStock;
use App\Models\User;
use Illuminate\Http\Request;

class DriverStockController extends Controller
{
    public function show(string $id)
    {
        $driver = User::findOrFail($id);
        
        if (!$driver->isDriver()) {
            return response()->json(['message' => 'User is not a driver'], 400);
        }

        $stock = DriverStock::where('driver_id', $id)
            ->with('product.category')
            ->get();

        return response()->json($stock);
    }

    public function myStock(Request $request)
    {
        $driver = $request->user();

        $stock = DriverStock::where('driver_id', $driver->id)
            ->with('product.category')
            ->get();

        return response()->json($stock);
    }
}

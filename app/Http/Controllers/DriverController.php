<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DriverController extends Controller
{
    public function index()
    {
        $drivers = User::where('type', 'driver')
            ->withCount(['sales', 'driverStock'])
            ->with(['sales' => function($query) {
                $query->selectRaw('driver_id, COUNT(*) as total_sales, SUM(total_amount) as total_revenue')
                    ->groupBy('driver_id');
            }])
            ->get()
            ->map(function($driver) {
                $totalSales = $driver->sales()->count();
                $totalRevenue = $driver->sales()->sum('total_amount');
                $totalStockItems = $driver->driverStock()->sum('quantity');
                
                return [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'email' => $driver->email,
                    'created_at' => $driver->created_at,
                    'total_sales' => $totalSales,
                    'total_revenue' => (float) $totalRevenue,
                    'total_stock_items' => $totalStockItems,
                ];
            });
        
        return response()->json($drivers);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $driver = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'type' => 'driver',
        ]);

        return response()->json([
            'message' => 'Driver created successfully',
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'email' => $driver->email,
                'type' => $driver->type,
            ],
        ], 201);
    }

    public function show(string $id)
    {
        $driver = User::where('type', 'driver')->findOrFail($id);
        
        // Get driver statistics
        $totalSales = $driver->sales()->count();
        $totalRevenue = $driver->sales()->sum('total_amount');
        $totalStockItems = $driver->driverStock()->sum('quantity');
        
        return response()->json([
            'id' => $driver->id,
            'name' => $driver->name,
            'email' => $driver->email,
            'created_at' => $driver->created_at,
            'stats' => [
                'total_sales' => $totalSales,
                'total_revenue' => (float) $totalRevenue,
                'total_stock_items' => $totalStockItems,
            ],
        ]);
    }

    public function update(Request $request, string $id)
    {
        $driver = User::where('type', 'driver')->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
            'password' => 'sometimes|string|min:6',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $driver->update($validated);

        return response()->json([
            'message' => 'Driver updated successfully',
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'email' => $driver->email,
                'type' => $driver->type,
            ],
        ]);
    }

    public function destroy(string $id)
    {
        $driver = User::where('type', 'driver')->findOrFail($id);
        $driver->delete();

        return response()->json(['message' => 'Driver deleted successfully']);
    }
}


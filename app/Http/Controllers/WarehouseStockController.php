<?php

namespace App\Http\Controllers;

use App\Models\WarehouseStock;
use Illuminate\Http\Request;

class WarehouseStockController extends Controller
{
    public function index()
    {
        $stock = WarehouseStock::with('product.category')->get();
        return response()->json($stock);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:0',
        ]);

        $stock = WarehouseStock::updateOrCreate(
            ['product_id' => $validated['product_id']],
            ['quantity' => $validated['quantity']]
        );

        return response()->json($stock->load('product'));
    }
}

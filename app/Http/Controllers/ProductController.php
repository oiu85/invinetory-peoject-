<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with(['category', 'warehouseStock'])
            ->get()
            ->map(function($product) {
                $warehouseQuantity = $product->warehouseStock ? $product->warehouseStock->quantity : 0;
                $totalDriverStock = $product->driverStock()->sum('quantity');
                $totalSold = $product->saleItems()->sum('quantity');
                
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'category_id' => $product->category_id,
                    'category' => $product->category,
                    'description' => $product->description,
                    'image' => $product->image,
                    'warehouse_quantity' => $warehouseQuantity,
                    'total_driver_stock' => $totalDriverStock,
                    'total_sold' => $totalSold,
                    'warehouse_stock' => $product->warehouseStock,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                ];
            });
        
        return response()->json($products);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
        ]);

        $product = Product::create($validated);

        // Create initial warehouse stock entry
        $product->warehouseStock()->create(['quantity' => 0]);

        return response()->json($product->load('category', 'warehouseStock'), 201);
    }

    public function update(Request $request, string $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0',
            'category_id' => 'sometimes|required|exists:categories,id',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
        ]);

        $product->update($validated);

        return response()->json($product->load('category', 'warehouseStock'));
    }

    public function destroy(string $id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    /**
     * Get all products for drivers (read-only, for stock requests)
     * Returns simplified product list without sensitive admin data
     * Includes warehouse stock quantity for validation
     */
    public function driverIndex()
    {
        $products = Product::with(['category', 'warehouseStock'])
            ->get()
            ->map(function($product) {
                $warehouseQuantity = $product->warehouseStock ? $product->warehouseStock->quantity : 0;
                
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'category_id' => $product->category_id,
                    'category' => $product->category ? [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                    ] : null,
                    'description' => $product->description,
                    'image' => $product->image,
                    'warehouse_quantity' => $warehouseQuantity,
                ];
            });
        
        return response()->json([
            'data' => $products,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with('products')
            ->get()
            ->map(function($category) {
                $productCount = $category->products()->count();
                $totalValue = $category->products()->sum('price');
                
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'product_count' => $productCount,
                    'total_value' => (float) $totalValue,
                    'products' => $category->products,
                    'created_at' => $category->created_at,
                    'updated_at' => $category->updated_at,
                ];
            });
        
        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $category = Category::create($validated);

        return response()->json($category, 201);
    }

    public function update(Request $request, string $id)
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $category->update($validated);

        return response()->json($category);
    }

    public function destroy(string $id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }
}

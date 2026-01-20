<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductDimensionRequest;
use App\Models\Product;
use App\Models\ProductDimension;
use Illuminate\Http\JsonResponse;

class ProductDimensionController extends Controller
{
    public function index(): JsonResponse
    {
        $dimensions = ProductDimension::with('product')->get();

        return response()->json($dimensions);
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $dimension = $product->productDimension;

        if (! $dimension) {
            return response()->json(['message' => 'Product dimensions not found'], 404);
        }

        return response()->json($dimension->load('product'));
    }

    public function store(StoreProductDimensionRequest $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        if ($product->productDimension) {
            return response()->json(['message' => 'Product dimensions already exist. Use update instead.'], 400);
        }

        $dimension = ProductDimension::create([
            'product_id' => $id,
            ...$request->validated(),
        ]);

        return response()->json($dimension->load('product'), 201);
    }

    public function update(StoreProductDimensionRequest $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $dimension = $product->productDimension;

        if (! $dimension) {
            return response()->json(['message' => 'Product dimensions not found'], 404);
        }

        $dimension->update($request->validated());

        return response()->json($dimension->load('product'));
    }

    public function destroy(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $dimension = $product->productDimension;

        if (! $dimension) {
            return response()->json(['message' => 'Product dimensions not found'], 404);
        }

        $dimension->delete();

        return response()->json(['message' => 'Product dimensions deleted successfully']);
    }
}

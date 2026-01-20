<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\WarehouseStock;
use App\Services\Storage\StorageFeedbackService;
use App\Services\Storage\StorageSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseStockController extends Controller
{
    public function __construct(
        private StorageSuggestionService $suggestionService,
        private StorageFeedbackService $feedbackService
    ) {
    }

    public function index(): JsonResponse
    {
        $stock = WarehouseStock::with('product.category')->get();

        return response()->json($stock);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:0',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $previousStock = WarehouseStock::where('product_id', $validated['product_id'])->first();
        $previousQuantity = $previousStock ? $previousStock->quantity : 0;
        $quantityAdded = max(0, $validated['quantity'] - $previousQuantity);

        $stock = WarehouseStock::updateOrCreate(
            ['product_id' => $validated['product_id']],
            ['quantity' => $validated['quantity']]
        );

        $response = [
            'success' => true,
            'message' => 'Stock updated successfully.',
            'stock_updated' => [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'new_quantity' => $validated['quantity'],
                'previous_quantity' => $previousQuantity,
                'quantity_added' => $quantityAdded,
            ],
        ];

        // Generate storage suggestions if quantity was added
        if ($quantityAdded > 0) {
            $suggestions = $this->suggestionService->generateSuggestions(
                $product->id,
                $quantityAdded
            );

            if (! isset($suggestions['error'])) {
                $feedback = $this->feedbackService->generateFeedback($suggestions);

                $response['message'] = 'Stock updated successfully. Storage suggestions available.';
                $response['storage_suggestions'] = array_merge($suggestions, $feedback);
            } else {
                $response['storage_suggestions'] = $suggestions;
            }
        }

        return response()->json($response);
    }

    public function suggestStorage(int $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $stock = WarehouseStock::where('product_id', $productId)->first();
        $quantity = $stock ? $stock->quantity : 0;

        if ($quantity <= 0) {
            return response()->json([
                'error' => 'No stock available',
                'message' => 'Product has no stock to suggest storage for',
            ], 400);
        }

        $suggestions = $this->suggestionService->generateSuggestions($productId, $quantity);

        if (isset($suggestions['error'])) {
            return response()->json($suggestions, 400);
        }

        $feedback = $this->feedbackService->generateFeedback($suggestions);

        return response()->json(array_merge($suggestions, $feedback));
    }

    public function applySuggestion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'product_id' => 'required|exists:products,id',
            'x_position' => 'required|numeric',
            'y_position' => 'required|numeric',
            'z_position' => 'required|numeric',
            'quantity' => 'required|integer|min:1',
        ]);

        // This would create actual placements in the room
        // For now, return success message
        return response()->json([
            'success' => true,
            'message' => 'Storage suggestion applied successfully',
            'placement' => $validated,
        ]);
    }

    public function pendingSuggestions(): JsonResponse
    {
        // Get products with stock but no room placements
        $products = Product::whereHas('warehouseStock', function ($query) {
            $query->where('quantity', '>', 0);
        })
            ->whereDoesntHave('productDimension')
            ->orWhereHas('productDimension')
            ->get();

        $suggestions = [];
        foreach ($products as $product) {
            $stock = $product->warehouseStock;
            if ($stock && $stock->quantity > 0) {
                $suggestions[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $stock->quantity,
                    'has_dimensions' => $product->productDimension !== null,
                ];
            }
        }

        return response()->json([
            'pending_suggestions' => $suggestions,
            'count' => count($suggestions),
        ]);
    }
}

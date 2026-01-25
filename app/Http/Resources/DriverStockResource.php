<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverStockResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = $this->product;
        $stockValue = $this->quantity * $product->price;
        $lowStockThreshold = 10;

        return [
            'id' => $this->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_description' => $product->description,
            'product_image' => $product->image,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
            ] : null,
            'price' => (float) $product->price,
            'quantity' => (int) $this->quantity,
            'stock_value' => (float) $stockValue,
            'is_low_stock' => $this->quantity < $lowStockThreshold,
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

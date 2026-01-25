<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'customer_name' => $this->customer_name,
            'total_amount' => (float) $this->total_amount,
            'driver_name' => $this->whenLoaded('driver', fn() => $this->driver->name),
            'items_count' => $this->whenLoaded('items', fn() => $this->items->count()),
            'items' => SaleItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at->toIso8601String(),
            'formatted_date' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}

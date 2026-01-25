<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'quick_stats' => [
                'today_sales' => $this->resource['quick_stats']['today_sales'] ?? 0,
                'today_revenue' => (float) ($this->resource['quick_stats']['today_revenue'] ?? 0),
                'available_products' => $this->resource['quick_stats']['available_products'] ?? 0,
                'low_stock_alerts' => $this->resource['quick_stats']['low_stock_alerts'] ?? 0,
            ],
            'recent_sales' => $this->resource['recent_sales'] ?? [],
            'low_stock_products' => $this->resource['low_stock_products'] ?? [],
        ];
    }
}

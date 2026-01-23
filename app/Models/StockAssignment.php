<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAssignment extends Model
{
    protected $fillable = [
        'driver_id',
        'product_id',
        'room_id',
        'quantity',
        'assigned_from',
        'product_price_at_assignment',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'product_price_at_assignment' => 'decimal:2',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}

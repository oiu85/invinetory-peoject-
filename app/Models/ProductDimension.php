<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDimension extends Model
{
    protected $fillable = [
        'product_id',
        'width',
        'depth',
        'height',
        'weight',
        'rotatable',
        'fragile',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'decimal:2',
            'depth' => 'decimal:2',
            'height' => 'decimal:2',
            'weight' => 'decimal:2',
            'rotatable' => 'boolean',
            'fragile' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getBaseAreaAttribute(): float
    {
        return $this->width * $this->depth;
    }

    public function getVolumeAttribute(): float
    {
        return $this->width * $this->depth * $this->height;
    }
}

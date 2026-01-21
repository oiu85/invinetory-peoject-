<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemPlacement extends Model
{
    protected $fillable = [
        'room_layout_id',
        'product_id',
        'warehouse_stock_id',
        'x_position',
        'y_position',
        'z_position',
        'width',
        'depth',
        'height',
        'rotation',
        'layer_index',
        'stack_id',
        'stack_position',
        'stack_base_x',
        'stack_base_y',
        'items_below_count',
    ];

    protected function casts(): array
    {
        return [
            'x_position' => 'decimal:2',
            'y_position' => 'decimal:2',
            'z_position' => 'decimal:2',
            'width' => 'decimal:2',
            'depth' => 'decimal:2',
            'height' => 'decimal:2',
            'layer_index' => 'integer',
            'stack_id' => 'integer',
            'stack_position' => 'integer',
            'stack_base_x' => 'decimal:2',
            'stack_base_y' => 'decimal:2',
            'items_below_count' => 'integer',
        ];
    }

    /**
     * Set the rotation attribute, ensuring it's always a string.
     */
    public function setRotationAttribute($value): void
    {
        $this->attributes['rotation'] = (string) $value;
    }

    public function roomLayout(): BelongsTo
    {
        return $this->belongsTo(RoomLayout::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouseStock(): BelongsTo
    {
        return $this->belongsTo(WarehouseStock::class);
    }
}

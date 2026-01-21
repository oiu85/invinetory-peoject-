<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomLayout extends Model
{
    protected $fillable = [
        'room_id',
        'algorithm_used',
        'utilization_percentage',
        'total_items_placed',
        'total_items_attempted',
        'layout_data',
        'compartment_config',
        'grid_columns',
        'grid_rows',
    ];

    protected function casts(): array
    {
        return [
            'utilization_percentage' => 'decimal:2',
            'total_items_placed' => 'integer',
            'total_items_attempted' => 'integer',
            'layout_data' => 'array',
            'compartment_config' => 'array',
            'grid_columns' => 'integer',
            'grid_rows' => 'integer',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(ItemPlacement::class);
    }

    /**
     * Get compartment configuration.
     *
     * @return array|null
     */
    public function getCompartmentsAttribute(): ?array
    {
        return $this->compartment_config;
    }

    /**
     * Get grid dimensions.
     *
     * @return array
     */
    public function getGridAttribute(): array
    {
        return [
            'columns' => $this->grid_columns ?? 0,
            'rows' => $this->grid_rows ?? 0,
        ];
    }
}

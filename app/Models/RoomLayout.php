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
    ];

    protected function casts(): array
    {
        return [
            'utilization_percentage' => 'decimal:2',
            'total_items_placed' => 'integer',
            'total_items_attempted' => 'integer',
            'layout_data' => 'array',
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
}

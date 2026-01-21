<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'width',
        'depth',
        'height',
        'warehouse_id',
        'status',
        'max_weight',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'decimal:2',
            'depth' => 'decimal:2',
            'height' => 'decimal:2',
            'max_weight' => 'decimal:2',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function layouts(): HasMany
    {
        return $this->hasMany(RoomLayout::class);
    }

    public function roomStocks(): HasMany
    {
        return $this->hasMany(RoomStock::class);
    }

    public function getVolumeAttribute(): float
    {
        return $this->width * $this->depth * $this->height;
    }

    public function getFloorAreaAttribute(): float
    {
        return $this->width * $this->depth;
    }
}

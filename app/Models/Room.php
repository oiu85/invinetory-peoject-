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
        'door_x',
        'door_y',
        'door_width',
        'door_height',
        'door_wall',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'decimal:2',
            'depth' => 'decimal:2',
            'height' => 'decimal:2',
            'max_weight' => 'decimal:2',
            'door_x' => 'decimal:2',
            'door_y' => 'decimal:2',
            'door_width' => 'decimal:2',
            'door_height' => 'decimal:2',
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

    /**
     * Get door configuration as array.
     *
     * @return array|null
     */
    public function getDoorAttribute(): ?array
    {
        if (!$this->door_x) {
            return null;
        }

        return [
            'x' => $this->door_x,
            'y' => $this->door_y,
            'width' => $this->door_width,
            'height' => $this->door_height,
            'wall' => $this->door_wall,
        ];
    }
}

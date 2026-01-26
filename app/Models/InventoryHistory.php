<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryHistory extends Model
{
    protected $table = 'inventory_history';

    protected $fillable = [
        'driver_id',
        'performed_at',
        'stock_snapshot',
        'earnings_before_reset',
        'earnings_after_reset',
        'total_stock_value',
        'total_cost_value',
        'period_start_date',
        'period_end_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
            'stock_snapshot' => 'array',
            'earnings_before_reset' => 'decimal:2',
            'earnings_after_reset' => 'decimal:2',
            'total_stock_value' => 'decimal:2',
            'total_cost_value' => 'decimal:2',
            'period_start_date' => 'date',
            'period_end_date' => 'date',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}

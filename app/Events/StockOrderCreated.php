<?php

namespace App\Events;

use App\Models\StockOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockOrderCreated
{
    use Dispatchable, SerializesModels;

    public StockOrder $stockOrder;

    /**
     * Create a new event instance.
     */
    public function __construct(StockOrder $stockOrder)
    {
        $this->stockOrder = $stockOrder;
    }
}

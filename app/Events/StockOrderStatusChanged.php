<?php

namespace App\Events;

use App\Models\StockOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockOrderStatusChanged
{
    use Dispatchable, SerializesModels;

    public StockOrder $stockOrder;
    public string $oldStatus;
    public string $newStatus;

    /**
     * Create a new event instance.
     */
    public function __construct(StockOrder $stockOrder, string $oldStatus, string $newStatus)
    {
        $this->stockOrder = $stockOrder;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }
}

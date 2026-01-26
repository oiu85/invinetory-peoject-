<?php

namespace App\Events;

use App\Models\Sale;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SaleCreated
{
    use Dispatchable, SerializesModels;

    public Sale $sale;

    /**
     * Create a new event instance.
     */
    public function __construct(Sale $sale)
    {
        $this->sale = $sale;
    }
}

<?php

namespace App\Listeners;

use App\Events\SaleCreated;
use App\Services\FcmNotificationService;
use Illuminate\Support\Facades\Log;

class NotifyAdminOnSale
{
    private FcmNotificationService $fcmService;

    /**
     * Create the event listener.
     */
    public function __construct(FcmNotificationService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Handle the event.
     */
    public function handle(SaleCreated $event): void
    {
        $sale = $event->sale;
        $sale->load(['driver', 'items.product']);

        $driverName = $sale->driver->name ?? 'Unknown Driver';
        $itemsCount = $sale->items->count();
        $totalAmount = number_format($sale->total_amount, 2);

        $title = 'New Sale Completed';
        $body = "{$driverName} completed a sale of {$itemsCount} item(s) for \${$totalAmount}";

        $this->fcmService->sendToAdmins(
            $title,
            $body,
            [
                'type' => 'sale_created',
                'sale_id' => $sale->id,
                'driver_id' => $sale->driver_id,
                'driver_name' => $driverName,
                'invoice_number' => $sale->invoice_number,
                'total_amount' => (float) $sale->total_amount,
                'items_count' => $itemsCount,
            ]
        );
    }
}

<?php

namespace App\Listeners;

use App\Events\StockOrderCreated;
use App\Services\FcmNotificationService;
use Illuminate\Support\Facades\Log;

class NotifyAdminOnOrderCreated
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
    public function handle(StockOrderCreated $event): void
    {
        $order = $event->stockOrder;
        $order->load(['product', 'driver']);

        $driverName = $order->driver->name ?? 'Unknown Driver';
        $productName = $order->product->name ?? 'Unknown Product';
        $quantity = $order->quantity;

        $title = 'New Stock Order Request';
        $body = "Driver {$driverName} requested {$quantity} units of {$productName}";

        $this->fcmService->sendToAdmins(
            $title,
            $body,
            [
                'type' => 'stock_order_created',
                'order_id' => $order->id,
                'driver_id' => $order->driver_id,
                'driver_name' => $driverName,
                'product_id' => $order->product_id,
                'product_name' => $productName,
                'quantity' => $quantity,
            ]
        );
    }
}

<?php

namespace App\Listeners;

use App\Events\StockOrderCreated;
use App\Helpers\NotificationTranslationHelper;
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
        try {
            $order = $event->stockOrder;
            $order->load(['product', 'driver']);

            $driverName = $order->driver->name ?? 'سائق غير معروف';
            $productName = $order->product->name ?? 'منتج غير معروف';
            $quantity = $order->quantity;

            // Get Arabic notification message
            $notification = NotificationTranslationHelper::get('stock_order_created', [
                'driver_name' => $driverName,
                'quantity' => $quantity,
                'product_name' => $productName,
            ]);

            Log::info('Notifying admins about new stock order', [
                'order_id' => $order->id,
                'driver_id' => $order->driver_id,
                'product_id' => $order->product_id,
                'quantity' => $quantity,
            ]);

            $result = $this->fcmService->sendToAdmins(
                $notification['title'],
                $notification['body'],
                [
                    'type' => 'stock_order_created',
                    'order_id' => (string) $order->id,
                    'driver_id' => (string) $order->driver_id,
                    'driver_name' => $driverName,
                    'product_id' => (string) $order->product_id,
                    'product_name' => $productName,
                    'quantity' => (string) $quantity,
                ]
            );

            if ($result) {
                Log::info('Successfully sent Arabic notification to admins', [
                    'order_id' => $order->id,
                    'language' => 'ar',
                ]);
            } else {
                Log::warning('Failed to send notification to admins', [
                    'order_id' => $order->id,
                    'reason' => 'FCM service returned false',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error in NotifyAdminOnOrderCreated listener: ' . $e->getMessage(), [
                'order_id' => $event->stockOrder->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

<?php

namespace App\Listeners;

use App\Events\StockOrderStatusChanged;
use App\Helpers\NotificationTranslationHelper;
use App\Services\FcmNotificationService;
use Illuminate\Support\Facades\Log;

class NotifyDriverOnOrderStatusChange
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
    public function handle(StockOrderStatusChanged $event): void
    {
        try {
            $order = $event->stockOrder;
            $order->load(['product', 'driver']);

            $productName = $order->product->name ?? 'منتج غير معروف';
            $quantity = $order->quantity;

            // Determine notification message based on status
            if ($event->newStatus === 'approved') {
                $notification = NotificationTranslationHelper::get('stock_order_approved', [
                    'quantity' => $quantity,
                    'product_name' => $productName,
                ]);
            } elseif ($event->newStatus === 'rejected') {
                $reason = NotificationTranslationHelper::formatRejectionReason($order->rejection_reason);
                $notification = NotificationTranslationHelper::get('stock_order_rejected', [
                    'quantity' => $quantity,
                    'product_name' => $productName,
                    'reason' => $reason,
                ]);
            } else {
                // Only notify on status changes (approved/rejected)
                Log::info('Skipping notification for status change', [
                    'order_id' => $order->id,
                    'new_status' => $event->newStatus,
                ]);
                return;
            }

            Log::info('Notifying driver about stock order status change', [
                'order_id' => $order->id,
                'driver_id' => $order->driver_id,
                'status' => $event->newStatus,
            ]);

            $result = $this->fcmService->sendToUser(
                $order->driver_id,
                $notification['title'],
                $notification['body'],
                [
                    'type' => 'stock_order_status_changed',
                    'order_id' => (string) $order->id,
                    'product_id' => (string) $order->product_id,
                    'product_name' => $productName,
                    'quantity' => (string) $quantity,
                    'status' => $event->newStatus,
                    'rejection_reason' => $order->rejection_reason ?? '',
                ]
            );

            if ($result) {
                Log::info('Successfully sent Arabic notification to driver', [
                    'order_id' => $order->id,
                    'driver_id' => $order->driver_id,
                    'language' => 'ar',
                ]);
            } else {
                Log::warning('Failed to send notification to driver', [
                    'order_id' => $order->id,
                    'driver_id' => $order->driver_id,
                    'reason' => 'FCM service returned false or no tokens found',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error in NotifyDriverOnOrderStatusChange listener: ' . $e->getMessage(), [
                'order_id' => $event->stockOrder->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

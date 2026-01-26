<?php

namespace App\Listeners;

use App\Events\StockOrderStatusChanged;
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

            $productName = $order->product->name ?? 'Unknown Product';
            $quantity = $order->quantity;

            if ($event->newStatus === 'approved') {
                $title = 'Stock Order Approved';
                $body = "Your request for {$quantity} units of {$productName} has been approved";
            } elseif ($event->newStatus === 'rejected') {
                $title = 'Stock Order Rejected';
                $reason = $order->rejection_reason ? " Reason: {$order->rejection_reason}" : '';
                $body = "Your request for {$quantity} units of {$productName} has been rejected.{$reason}";
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
                $title,
                $body,
                [
                    'type' => 'stock_order_status_changed',
                    'order_id' => $order->id,
                    'product_id' => $order->product_id,
                    'product_name' => $productName,
                    'quantity' => $quantity,
                    'status' => $event->newStatus,
                    'rejection_reason' => $order->rejection_reason,
                ]
            );

            if ($result) {
                Log::info('Successfully sent notification to driver', [
                    'order_id' => $order->id,
                    'driver_id' => $order->driver_id,
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

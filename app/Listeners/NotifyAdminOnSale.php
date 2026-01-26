<?php

namespace App\Listeners;

use App\Events\SaleCreated;
use App\Helpers\NotificationTranslationHelper;
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
        try {
            $sale = $event->sale;
            $sale->load(['driver', 'items.product']);

            $driverName = $sale->driver->name ?? 'سائق غير معروف';
            $itemsCount = $sale->items->count();
            $totalAmount = number_format($sale->total_amount, 2);

            // Get Arabic notification message
            $notification = NotificationTranslationHelper::get('sale_created', [
                'driver_name' => $driverName,
                'items_count' => $itemsCount,
                'total_amount' => $totalAmount,
            ]);

            Log::info('Notifying admins about new sale', [
                'sale_id' => $sale->id,
                'driver_id' => $sale->driver_id,
                'driver_name' => $driverName,
                'invoice_number' => $sale->invoice_number,
                'total_amount' => $sale->total_amount,
                'items_count' => $itemsCount,
            ]);

            $result = $this->fcmService->sendToAdmins(
                $notification['title'],
                $notification['body'],
                [
                    'type' => 'sale_created',
                    'sale_id' => (string) $sale->id,
                    'driver_id' => (string) $sale->driver_id,
                    'driver_name' => $driverName,
                    'invoice_number' => $sale->invoice_number,
                    'total_amount' => (string) $sale->total_amount,
                    'items_count' => (string) $itemsCount,
                ]
            );

            if ($result) {
                Log::info('Successfully sent Arabic sale notification to admins', [
                    'sale_id' => $sale->id,
                    'language' => 'ar',
                ]);
            } else {
                Log::warning('Failed to send sale notification to admins', [
                    'sale_id' => $sale->id,
                    'reason' => 'FCM service returned false',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error in NotifyAdminOnSale listener: ' . $e->getMessage(), [
                'sale_id' => $event->sale->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

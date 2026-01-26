<?php

namespace App\Helpers;

/**
 * Helper class for translating notification messages to Arabic.
 * All notification messages in the system should use this helper to ensure Arabic language support.
 */
class NotificationTranslationHelper
{
    /**
     * Get translated notification messages in Arabic.
     * 
     * @param string $key
     * @param array $replacements
     * @return array ['title' => string, 'body' => string]
     */
    public static function get(string $key, array $replacements = []): array
    {
        $messages = self::getMessages();
        
        if (!isset($messages[$key])) {
            return [
                'title' => 'إشعار',
                'body' => 'لديك إشعار جديد',
            ];
        }

        $message = $messages[$key];
        
        // Replace placeholders with actual values
        foreach ($replacements as $placeholder => $value) {
            $message['title'] = str_replace("{{$placeholder}}", $value, $message['title']);
            $message['body'] = str_replace("{{$placeholder}}", $value, $message['body']);
        }

        return $message;
    }

    /**
     * Get all notification messages in Arabic.
     * 
     * @return array
     */
    private static function getMessages(): array
    {
        return [
            // Stock Order Created - Notify Admin
            'stock_order_created' => [
                'title' => 'طلب مخزون جديد',
                'body' => 'السائق {driver_name} طلب {quantity} وحدة من {product_name}',
            ],

            // Stock Order Approved - Notify Driver
            'stock_order_approved' => [
                'title' => 'تم الموافقة على طلب المخزون',
                'body' => 'تمت الموافقة على طلبك لـ {quantity} وحدة من {product_name}',
            ],

            // Stock Order Rejected - Notify Driver
            'stock_order_rejected' => [
                'title' => 'تم رفض طلب المخزون',
                'body' => 'تم رفض طلبك لـ {quantity} وحدة من {product_name}{reason}',
            ],

            // Sale Created - Notify Admin
            'sale_created' => [
                'title' => 'عملية بيع جديدة',
                'body' => '{driver_name} أكمل عملية بيع لـ {items_count} منتج بمبلغ {total_amount} دولار',
            ],

            // Stock Assigned - Notify Driver
            'stock_assigned' => [
                'title' => 'تم تعيين مخزون جديد',
                'body' => 'تم تعيين {quantity} وحدة من {product_name} لك',
            ],

            // Low Stock Alert - Notify Admin
            'low_stock_alert' => [
                'title' => 'تنبيه: مخزون منخفض',
                'body' => 'المنتج {product_name} وصل إلى الحد الأدنى. المخزون الحالي: {current_stock}',
            ],

            // Out of Stock Alert - Notify Admin
            'out_of_stock' => [
                'title' => 'تنبيه: نفاذ المخزون',
                'body' => 'المنتج {product_name} نفد من المخزون',
            ],

            // Stock Transfer Completed - Notify Driver
            'stock_transfer_completed' => [
                'title' => 'اكتمال نقل المخزون',
                'body' => 'تم نقل {quantity} وحدة من {product_name} بنجاح',
            ],

            // Payment Reminder - Notify Driver
            'payment_reminder' => [
                'title' => 'تذكير بالدفع',
                'body' => 'لديك رصيد مستحق بمبلغ {amount} دولار',
            ],

            // Target Achieved - Notify Driver
            'target_achieved' => [
                'title' => 'تهانينا! تم تحقيق الهدف',
                'body' => 'لقد حققت هدف المبيعات لهذا الشهر بمبلغ {amount} دولار',
            ],

            // New Product Available - Notify All
            'new_product_available' => [
                'title' => 'منتج جديد متاح',
                'body' => 'منتج جديد {product_name} متاح الآن في المخزون',
            ],

            // Price Update - Notify Drivers
            'price_update' => [
                'title' => 'تحديث السعر',
                'body' => 'تم تحديث سعر {product_name} من {old_price} إلى {new_price} دولار',
            ],

            // Account Activated - Notify Driver
            'account_activated' => [
                'title' => 'تم تفعيل حسابك',
                'body' => 'مرحباً {driver_name}! تم تفعيل حسابك بنجاح. يمكنك الآن البدء في العمل.',
            ],

            // Account Deactivated - Notify Driver
            'account_deactivated' => [
                'title' => 'تم إيقاف حسابك',
                'body' => 'تم إيقاف حسابك مؤقتاً. يرجى الاتصال بالإدارة للمزيد من المعلومات.',
            ],

            // Daily Report Ready - Notify Admin
            'daily_report_ready' => [
                'title' => 'التقرير اليومي جاهز',
                'body' => 'تقرير المبيعات اليومي بتاريخ {date} جاهز للمراجعة',
            ],

            // Monthly Report Ready - Notify Admin
            'monthly_report_ready' => [
                'title' => 'التقرير الشهري جاهز',
                'body' => 'تقرير المبيعات الشهري لشهر {month} جاهز للمراجعة',
            ],

            // System Maintenance - Notify All
            'system_maintenance' => [
                'title' => 'صيانة النظام',
                'body' => 'سيتم إجراء صيانة للنظام في {date} الساعة {time}. قد يكون النظام غير متاح مؤقتاً.',
            ],

            // Generic Notification
            'generic' => [
                'title' => 'إشعار',
                'body' => '{message}',
            ],

            // Custom Admin Notification (used when admin sends custom message)
            'admin_notification' => [
                'title' => '{title}',
                'body' => '{body}',
            ],
        ];
    }

    /**
     * Format rejection reason for notification.
     * 
     * @param string|null $reason
     * @return string
     */
    public static function formatRejectionReason(?string $reason): string
    {
        if (empty($reason)) {
            return '';
        }
        return ". السبب: {$reason}";
    }

    /**
     * Format currency amount in Arabic format.
     * 
     * @param float $amount
     * @return string
     */
    public static function formatCurrency(float $amount): string
    {
        return number_format($amount, 2) . ' دولار';
    }

    /**
     * Format quantity with Arabic unit.
     * 
     * @param int $quantity
     * @param string $unit (default: 'وحدة' for unit)
     * @return string
     */
    public static function formatQuantity(int $quantity, string $unit = 'وحدة'): string
    {
        return "{$quantity} {$unit}";
    }

    /**
     * Get month name in Arabic.
     * 
     * @param int $month (1-12)
     * @return string
     */
    public static function getArabicMonth(int $month): string
    {
        $months = [
            1 => 'يناير',
            2 => 'فبراير',
            3 => 'مارس',
            4 => 'أبريل',
            5 => 'مايو',
            6 => 'يونيو',
            7 => 'يوليو',
            8 => 'أغسطس',
            9 => 'سبتمبر',
            10 => 'أكتوبر',
            11 => 'نوفمبر',
            12 => 'ديسمبر',
        ];

        return $months[$month] ?? 'غير معروف';
    }

    /**
     * Format date in Arabic.
     * 
     * @param string $date
     * @return string
     */
    public static function formatDate(string $date): string
    {
        try {
            $timestamp = strtotime($date);
            if ($timestamp === false) {
                return $date;
            }

            $day = date('j', $timestamp);
            $month = self::getArabicMonth((int)date('n', $timestamp));
            $year = date('Y', $timestamp);

            return "{$day} {$month} {$year}";
        } catch (\Exception $e) {
            return $date;
        }
    }
}

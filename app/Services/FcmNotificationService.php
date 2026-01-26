<?php

namespace App\Services;

use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FcmNotificationService
{
    private $messaging;

    public function __construct()
    {
        try {
            $credentialsPath = config('firebase.credentials_path');
            
            if (!file_exists($credentialsPath)) {
                Log::warning('Firebase credentials file not found at: ' . $credentialsPath);
                return;
            }

            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $this->messaging = $factory->createMessaging();
        } catch (\Exception $e) {
            Log::error('Failed to initialize Firebase Messaging: ' . $e->getMessage());
        }
    }

    /**
     * Send notification to a specific user by user ID.
     *
     * @param int $userId
     * @param string $title
     * @param string $body
     * @param array $data
     * @return bool
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): bool
    {
        if (!$this->messaging) {
            return false;
        }

        $tokens = FcmToken::where('user_id', $userId)->pluck('token')->toArray();

        if (empty($tokens)) {
            Log::info("No FCM tokens found for user ID: {$userId}");
            return false;
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * Send notification to all admin users.
     *
     * @param string $title
     * @param string $body
     * @param array $data
     * @return bool
     */
    public function sendToAdmins(string $title, string $body, array $data = []): bool
    {
        if (!$this->messaging) {
            return false;
        }

        $adminIds = User::where('type', 'admin')->pluck('id')->toArray();
        $tokens = FcmToken::whereIn('user_id', $adminIds)->pluck('token')->toArray();

        if (empty($tokens)) {
            Log::info('No FCM tokens found for admin users');
            return false;
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * Send notification to all driver users.
     *
     * @param string $title
     * @param string $body
     * @param array $data
     * @return bool
     */
    public function sendToDrivers(string $title, string $body, array $data = []): bool
    {
        if (!$this->messaging) {
            return false;
        }

        $driverIds = User::where('type', 'driver')->pluck('id')->toArray();
        $tokens = FcmToken::whereIn('user_id', $driverIds)->pluck('token')->toArray();

        if (empty($tokens)) {
            Log::info('No FCM tokens found for driver users');
            return false;
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * Send notification to a specific FCM token.
     *
     * @param string $token
     * @param string $title
     * @param string $body
     * @param array $data
     * @return bool
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        return $this->sendToTokens([$token], $title, $body, $data);
    }

    /**
     * Send notification to multiple FCM tokens.
     *
     * @param array $tokens
     * @param string $title
     * @param string $body
     * @param array $data
     * @return bool
     */
    private function sendToTokens(array $tokens, string $title, string $body, array $data = []): bool
    {
        if (!$this->messaging || empty($tokens)) {
            return false;
        }

        try {
            $notification = Notification::create($title, $body);
            
            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData($data);

            $results = $this->messaging->sendMulticast($message, $tokens);

            // Remove invalid tokens
            $invalidTokens = [];
            foreach ($results->failures() as $failure) {
                $invalidTokens[] = $failure->target()->value();
            }

            if (!empty($invalidTokens)) {
                FcmToken::whereIn('token', $invalidTokens)->delete();
                Log::info('Removed invalid FCM tokens: ' . count($invalidTokens));
            }

            $successCount = $results->successes()->count();
            Log::info("Sent FCM notifications: {$successCount} successful, " . count($invalidTokens) . " failed");

            return $successCount > 0;
        } catch (\Exception $e) {
            Log::error('Failed to send FCM notifications: ' . $e->getMessage());
            return false;
        }
    }
}

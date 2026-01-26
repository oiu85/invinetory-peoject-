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
            
            Log::info('Initializing Firebase Messaging', [
                'credentials_path' => $credentialsPath,
                'file_exists' => file_exists($credentialsPath),
                'is_readable' => file_exists($credentialsPath) ? is_readable($credentialsPath) : false,
            ]);
            
            if (!file_exists($credentialsPath)) {
                Log::error('Firebase credentials file not found at: ' . $credentialsPath);
                Log::error('Please ensure the Firebase service account JSON file exists at this path');
                return;
            }

            if (!is_readable($credentialsPath)) {
                Log::error('Firebase credentials file is not readable: ' . $credentialsPath);
                return;
            }

            // Validate JSON file
            $credentialsContent = file_get_contents($credentialsPath);
            $credentials = json_decode($credentialsContent, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Firebase credentials file is not valid JSON: ' . json_last_error_msg());
                return;
            }

            if (!isset($credentials['project_id'])) {
                Log::error('Firebase credentials file is missing project_id');
                return;
            }

            Log::info('Firebase credentials validated', [
                'project_id' => $credentials['project_id'] ?? 'unknown',
            ]);

            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $this->messaging = $factory->createMessaging();
            
            Log::info('Firebase Messaging initialized successfully');
        } catch (\Kreait\Firebase\Exception\ServiceAccountDiscoveryFailed $e) {
            Log::error('Firebase Service Account Discovery Failed: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        } catch (\Kreait\Firebase\Exception\InvalidArgumentException $e) {
            Log::error('Firebase Invalid Argument: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to initialize Firebase Messaging: ' . $e->getMessage(), [
                'exception_type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
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
        try {
            if (!$this->messaging) {
                Log::warning("FCM messaging not initialized, cannot send to user ID: {$userId}");
                return false;
            }

            $user = User::find($userId);
            if (!$user) {
                Log::warning("User not found: {$userId}");
                return false;
            }

            $tokens = FcmToken::where('user_id', $userId)->pluck('token')->toArray();

            if (empty($tokens)) {
                Log::warning("No FCM tokens found for user ID: {$userId}", [
                    'user_name' => $user->name,
                    'user_type' => $user->type,
                ]);
                return false;
            }

            Log::info('Sending notification to user', [
                'user_id' => $userId,
                'user_name' => $user->name,
                'token_count' => count($tokens),
                'title' => $title,
            ]);

            return $this->sendToTokens($tokens, $title, $body, $data);
        } catch (\Exception $e) {
            Log::error('Exception in sendToUser: ' . $e->getMessage(), [
                'user_id' => $userId,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
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
        try {
            if (!$this->messaging) {
                Log::warning('FCM messaging not initialized, cannot send to admins');
                return false;
            }

            $adminIds = User::where('type', 'admin')->pluck('id')->toArray();
            
            if (empty($adminIds)) {
                Log::warning('No admin users found in database');
                return false;
            }

            $tokens = FcmToken::whereIn('user_id', $adminIds)->pluck('token')->toArray();

            if (empty($tokens)) {
                Log::warning('No FCM tokens found for admin users', [
                    'admin_count' => count($adminIds),
                    'admin_ids' => $adminIds,
                ]);
                return false;
            }

            Log::info('Sending notification to admins', [
                'admin_count' => count($adminIds),
                'token_count' => count($tokens),
                'title' => $title,
            ]);

            return $this->sendToTokens($tokens, $title, $body, $data);
        } catch (\Exception $e) {
            Log::error('Exception in sendToAdmins: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
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
        try {
            if (!$this->messaging) {
                Log::warning('FCM messaging not initialized, cannot send to drivers');
                return false;
            }

            $driverIds = User::where('type', 'driver')->pluck('id')->toArray();
            
            if (empty($driverIds)) {
                Log::warning('No driver users found in database');
                return false;
            }

            $tokens = FcmToken::whereIn('user_id', $driverIds)->pluck('token')->toArray();

            if (empty($tokens)) {
                Log::warning('No FCM tokens found for driver users', [
                    'driver_count' => count($driverIds),
                    'driver_ids' => $driverIds,
                ]);
                return false;
            }

            Log::info('Sending notification to drivers', [
                'driver_count' => count($driverIds),
                'token_count' => count($tokens),
                'title' => $title,
            ]);

            return $this->sendToTokens($tokens, $title, $body, $data);
        } catch (\Exception $e) {
            Log::error('Exception in sendToDrivers: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
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
        if (!$this->messaging) {
            Log::error('FCM messaging service not initialized');
            return false;
        }

        if (empty($tokens)) {
            Log::warning('No FCM tokens provided for sending');
            return false;
        }

        try {
            // Validate title and body
            if (empty(trim($title))) {
                Log::error('FCM notification title is empty');
                return false;
            }
            
            if (empty(trim($body))) {
                Log::error('FCM notification body is empty');
                return false;
            }

            // Convert all data values to strings (Firebase requirement)
            $stringData = [];
            foreach ($data as $key => $value) {
                // Ensure key is a string
                $keyStr = (string) $key;
                
                if ($value === null) {
                    $stringData[$keyStr] = '';
                } elseif (is_bool($value)) {
                    $stringData[$keyStr] = $value ? '1' : '0';
                } elseif (is_array($value) || is_object($value)) {
                    $stringData[$keyStr] = json_encode($value);
                } else {
                    $stringData[$keyStr] = (string) $value;
                }
            }

            Log::info('Creating FCM notification', [
                'token_count' => count($tokens),
                'title' => $title,
                'data_keys' => array_keys($stringData),
            ]);

            $notification = Notification::create(trim($title), trim($body));
            
            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData($stringData);

            Log::info('Sending FCM multicast message', [
                'token_count' => count($tokens),
            ]);

            $results = $this->messaging->sendMulticast($message, $tokens);

            // Remove invalid tokens
            $invalidTokens = [];
            $failureReasons = [];
            foreach ($results->failures() as $failure) {
                try {
                    $token = $failure->target()->value();
                    $invalidTokens[] = $token;
                    
                    // Try to get error details if available
                    try {
                        $error = $failure->error();
                        $failureReasons[] = [
                            'token' => substr($token, 0, 20) . '...',
                            'error_code' => $error->getCode(),
                            'error_message' => $error->getMessage(),
                        ];
                    } catch (\Exception $e) {
                        // Error object might not be available
                        $failureReasons[] = [
                            'token' => substr($token, 0, 20) . '...',
                            'error' => 'Unable to get error details',
                        ];
                    }
                } catch (\Exception $e) {
                    Log::warning('Error processing failure: ' . $e->getMessage());
                }
            }

            if (!empty($invalidTokens)) {
                FcmToken::whereIn('token', $invalidTokens)->delete();
                Log::warning('Removed invalid FCM tokens', [
                    'count' => count($invalidTokens),
                    'reasons' => $failureReasons,
                ]);
            }

            $successCount = $results->successes()->count();
            Log::info("FCM notification results", [
                'successful' => $successCount,
                'failed' => count($invalidTokens),
                'total' => count($tokens),
            ]);

            return $successCount > 0;
        } catch (\Kreait\Firebase\Exception\MessagingException $e) {
            Log::error('Firebase Messaging error: ' . $e->getMessage(), [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to send FCM notifications: ' . $e->getMessage(), [
                'exception_type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }
}

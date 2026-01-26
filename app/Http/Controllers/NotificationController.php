<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FcmNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    private FcmNotificationService $fcmService;

    public function __construct(FcmNotificationService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Send notification to a specific driver.
     *
     * @param Request $request
     * @param int $driverId
     * @return JsonResponse
     */
    public function sendToDriver(Request $request, int $driverId): JsonResponse
    {
        try {
            $admin = $request->user();

            if (!$admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            // Validate input
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'body' => 'required|string|max:1000',
                'data' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Verify driver exists and is a driver
            $driver = User::find($driverId);

            if (!$driver) {
                Log::warning('Driver not found for notification', [
                    'driver_id' => $driverId,
                    'admin_id' => $admin->id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Driver not found',
                ], 404);
            }

            if (!$driver->isDriver()) {
                Log::warning('User is not a driver', [
                    'user_id' => $driverId,
                    'user_type' => $driver->type,
                    'admin_id' => $admin->id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'User is not a driver',
                ], 400);
            }

            $title = $request->input('title');
            $body = $request->input('body');
            $data = $request->input('data');

            // Ensure data is an array or null
            if ($data === null || $data === '') {
                $data = [];
            } elseif (!is_array($data)) {
                Log::warning('Invalid data format received', [
                    'data_type' => gettype($data),
                    'data' => $data,
                ]);
                $data = [];
            }

            // Add notification type to data (ensure all values are scalar for Firebase)
            $data['type'] = 'admin_notification';
            $data['sent_by'] = (string) $admin->id; // Convert to string for Firebase
            $data['sent_by_name'] = (string) $admin->name;

            Log::info('Admin sending notification to driver', [
                'admin_id' => $admin->id,
                'admin_name' => $admin->name,
                'driver_id' => $driverId,
                'driver_name' => $driver->name,
                'title' => $title,
            ]);

            // Check if FCM service is available
            if (!$this->fcmService) {
                Log::error('FCM service not available');
                return response()->json([
                    'success' => false,
                    'message' => 'Notification service is not available. Please contact administrator.',
                ], 503);
            }

            // Check Firebase credentials before attempting to send
            $credentialsPath = config('firebase.credentials_path');
            if (!file_exists($credentialsPath)) {
                Log::error('Firebase credentials file not found', [
                    'path' => $credentialsPath,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Firebase credentials file not found. Please configure Firebase service account.',
                    'debug' => config('app.debug') ? ['credentials_path' => $credentialsPath] : null,
                ], 500);
            }

            // Wrap in try-catch to handle any exceptions from FCM service
            try {
                $result = $this->fcmService->sendToUser(
                    $driverId,
                    $title,
                    $body,
                    $data
                );
            } catch (\Kreait\Firebase\Exception\MessagingException $fcmException) {
                Log::error('Firebase Messaging Exception: ' . $fcmException->getMessage(), [
                    'driver_id' => $driverId,
                    'code' => $fcmException->getCode(),
                    'file' => $fcmException->getFile(),
                    'line' => $fcmException->getLine(),
                ]);
                $result = false;
            } catch (\Exception $fcmException) {
                Log::error('Exception in FCM service sendToUser: ' . $fcmException->getMessage(), [
                    'driver_id' => $driverId,
                    'exception_type' => get_class($fcmException),
                    'file' => $fcmException->getFile(),
                    'line' => $fcmException->getLine(),
                    'trace' => $fcmException->getTraceAsString(),
                ]);
                $result = false;
            }

            if ($result) {
                Log::info('Notification sent successfully to driver', [
                    'driver_id' => $driverId,
                    'admin_id' => $admin->id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Notification sent successfully to driver',
                    'data' => [
                        'driver_id' => $driverId,
                        'driver_name' => $driver->name,
                        'title' => $title,
                    ],
                ]);
            } else {
                // Check if driver has FCM tokens registered
                $tokenCount = \App\Models\FcmToken::where('user_id', $driverId)->count();
                
                Log::warning('Failed to send notification to driver', [
                    'driver_id' => $driverId,
                    'admin_id' => $admin->id,
                    'token_count' => $tokenCount,
                    'reason' => 'FCM service returned false',
                ]);

                if ($tokenCount === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to send notification. Driver does not have FCM token registered. Please ask the driver to open the app.',
                    ], 400);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to send notification. Please check server logs for details.',
                    ], 500);
                }
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error sending notification to driver: ' . $e->getMessage(), [
                'driver_id' => $driverId,
                'admin_id' => $request->user()?->id ?? null,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while sending the notification. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Send notification to all drivers (bulk).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendToAllDrivers(Request $request): JsonResponse
    {
        $admin = $request->user();

        // Validate input
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:1000',
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $title = $request->input('title');
            $body = $request->input('body');
            $data = $request->input('data');

            // Ensure data is an array or null
            if ($data === null || $data === '') {
                $data = [];
            } elseif (!is_array($data)) {
                Log::warning('Invalid data format received in bulk notification', [
                    'data_type' => gettype($data),
                    'data' => $data,
                ]);
                $data = [];
            }

            // Add notification type to data (ensure all values are scalar for Firebase)
            $data['type'] = 'admin_notification_bulk';
            $data['sent_by'] = (string) $admin->id; // Convert to string for Firebase
            $data['sent_by_name'] = (string) $admin->name;

            // Get driver count for logging
            $driverCount = User::where('type', 'driver')->count();

            Log::info('Admin sending bulk notification to all drivers', [
                'admin_id' => $admin->id,
                'admin_name' => $admin->name,
                'driver_count' => $driverCount,
                'title' => $title,
            ]);

            $result = $this->fcmService->sendToDrivers(
                $title,
                $body,
                $data
            );

            if ($result) {
                Log::info('Bulk notification sent successfully to drivers', [
                    'admin_id' => $admin->id,
                    'driver_count' => $driverCount,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Notification sent successfully to all drivers',
                    'data' => [
                        'driver_count' => $driverCount,
                        'title' => $title,
                    ],
                ]);
            } else {
                Log::warning('Failed to send bulk notification to drivers', [
                    'admin_id' => $admin->id,
                    'driver_count' => $driverCount,
                    'reason' => 'FCM service returned false or no tokens found',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send notification. No drivers may have FCM tokens registered.',
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error sending bulk notification to drivers: ' . $e->getMessage(), [
                'admin_id' => $admin->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
            ], 500);
        }
    }
}

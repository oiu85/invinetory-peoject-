<?php

namespace App\Http\Controllers;

use App\Models\FcmToken;
use App\Models\User;
use App\Services\FcmNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FcmDiagnosticController extends Controller
{
    private FcmNotificationService $fcmService;

    public function __construct(FcmNotificationService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Get comprehensive FCM diagnostic information
     * Admin only endpoint
     */
    public function diagnostics(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user || $user->type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Get all admin users
        $adminUsers = User::where('type', 'admin')->get();
        
        // Get all admin tokens
        $adminTokens = FcmToken::whereIn('user_id', $adminUsers->pluck('id'))
            ->get();

        // Get all driver users
        $driverUsers = User::where('type', 'driver')->get();
        
        // Get all driver tokens
        $driverTokens = FcmToken::whereIn('user_id', $driverUsers->pluck('id'))
            ->get();

        // Check FCM service initialization
        $fcmInitialized = $this->fcmService ? true : false;

        return response()->json([
            'success' => true,
            'data' => [
                'fcm_service' => [
                    'initialized' => $fcmInitialized,
                ],
                'admins' => [
                    'total_count' => $adminUsers->count(),
                    'users' => $adminUsers->map(function ($admin) {
                        return [
                            'id' => $admin->id,
                            'name' => $admin->name,
                            'email' => $admin->email,
                        ];
                    }),
                    'tokens' => [
                        'total_count' => $adminTokens->count(),
                        'by_platform' => [
                            'web' => $adminTokens->where('platform', 'web')->count(),
                            'android' => $adminTokens->where('platform', 'android')->count(),
                            'ios' => $adminTokens->where('platform', 'ios')->count(),
                        ],
                        'details' => $adminTokens->map(function ($token) {
                            return [
                                'id' => $token->id,
                                'user_id' => $token->user_id,
                                'platform' => $token->platform,
                                'token_preview' => substr($token->token, 0, 30) . '...',
                                'last_used_at' => $token->last_used_at?->toIso8601String(),
                                'created_at' => $token->created_at->toIso8601String(),
                            ];
                        }),
                    ],
                ],
                'drivers' => [
                    'total_count' => $driverUsers->count(),
                    'users' => $driverUsers->map(function ($driver) {
                        return [
                            'id' => $driver->id,
                            'name' => $driver->name,
                            'email' => $driver->email,
                        ];
                    }),
                    'tokens' => [
                        'total_count' => $driverTokens->count(),
                        'by_platform' => [
                            'web' => $driverTokens->where('platform', 'web')->count(),
                            'android' => $driverTokens->where('platform', 'android')->count(),
                            'ios' => $driverTokens->where('platform', 'ios')->count(),
                        ],
                        'details' => $driverTokens->map(function ($token) {
                            return [
                                'id' => $token->id,
                                'user_id' => $token->user_id,
                                'platform' => $token->platform,
                                'token_preview' => substr($token->token, 0, 30) . '...',
                                'last_used_at' => $token->last_used_at?->toIso8601String(),
                                'created_at' => $token->created_at->toIso8601String(),
                            ];
                        }),
                    ],
                ],
            ],
        ]);
    }

    /**
     * Send a test notification to admins
     * Admin only endpoint
     */
    public function testNotification(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user || $user->type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        try {
            $title = 'Test Notification';
            $body = 'This is a test notification from the diagnostic endpoint';
            
            Log::info('Sending test notification to admins', [
                'requested_by' => $user->id,
            ]);

            $result = $this->fcmService->sendToAdmins(
                $title,
                $body,
                [
                    'type' => 'test_notification',
                    'timestamp' => now()->toIso8601String(),
                ]
            );

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Test notification sent successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send test notification. Check logs for details.',
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error sending test notification: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error sending test notification: ' . $e->getMessage(),
            ], 500);
        }
    }
}

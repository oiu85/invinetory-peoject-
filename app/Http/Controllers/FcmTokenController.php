<?php

namespace App\Http\Controllers;

use App\Models\FcmToken;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class FcmTokenController extends Controller
{
    /**
     * Register or update FCM token for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'platform' => 'required|in:web,android,ios',
            'device_id' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        if (!$user) {
            Log::warning('FCM token registration attempted without authentication', [
                'platform' => $validated['platform'] ?? 'unknown',
                'token_preview' => substr($validated['token'] ?? '', 0, 20) . '...',
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        Log::info('FCM token registration request received', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_type' => $user->type,
            'platform' => $validated['platform'],
            'device_id' => $validated['device_id'] ?? null,
            'token_preview' => substr($validated['token'], 0, 20) . '...',
        ]);

        try {
            // Since token has a unique constraint, we need to handle conflicts
            // Strategy: Delete any existing token with the same value (globally unique)
            // Then create/update the token for this user
            
            // First, delete the token if it exists for another user
            // (Token should be globally unique - one device = one token)
            $deletedOtherUser = FcmToken::where('token', $validated['token'])
                ->where('user_id', '!=', $user->id)
                ->delete();

            if ($deletedOtherUser > 0) {
                Log::info('Removed FCM token from other user', [
                    'user_id' => $user->id,
                    'deleted_count' => $deletedOtherUser,
                ]);
            }

            // Also delete old tokens for this user with the same device_id (if provided)
            // This ensures one token per device per user
            $deletedOldTokens = 0;
            if (!empty($validated['device_id'])) {
                $deletedOldTokens = FcmToken::where('user_id', $user->id)
                    ->where('device_id', $validated['device_id'])
                    ->where('token', '!=', $validated['token'])
                    ->delete();
                
                if ($deletedOldTokens > 0) {
                    Log::info('Removed old FCM tokens for same device', [
                        'user_id' => $user->id,
                        'device_id' => $validated['device_id'],
                        'deleted_count' => $deletedOldTokens,
                    ]);
                }
            }

            // Check if token already exists for this user
            $existingToken = FcmToken::where('user_id', $user->id)
                ->where('token', $validated['token'])
                ->first();

            // Now update or create the token for this user
            $fcmToken = FcmToken::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'token' => $validated['token'],
                ],
                [
                    'platform' => $validated['platform'],
                    'device_id' => $validated['device_id'] ?? null,
                    'last_used_at' => now(),
                ]
            );

            $action = $existingToken ? 'updated' : 'created';
            
            Log::info("FCM token {$action} successfully", [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_type' => $user->type,
                'token_id' => $fcmToken->id,
                'platform' => $fcmToken->platform,
                'device_id' => $fcmToken->device_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'FCM token registered successfully',
                'data' => [
                    'id' => $fcmToken->id,
                    'platform' => $fcmToken->platform,
                    'action' => $action,
                ],
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle database-specific errors (like unique constraint violations)
            Log::error('Database error while registering FCM token', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_type' => $user->type,
                'token_preview' => substr($validated['token'], 0, 20) . '...',
                'platform' => $validated['platform'],
                'error_code' => $e->getCode(),
                'error_message' => $e->getMessage(),
                'sql_state' => $e->errorInfo[0] ?? null,
                'sql_code' => $e->errorInfo[1] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to register FCM token due to database constraint',
                'error' => 'Database error occurred',
            ], 500);
        } catch (\Exception $e) {
            // Handle other exceptions
            Log::error('Failed to register FCM token', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_type' => $user->type,
                'token_preview' => substr($validated['token'], 0, 20) . '...',
                'platform' => $validated['platform'],
                'exception_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to register FCM token',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove FCM token for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        try {
            $deleted = FcmToken::where('user_id', $user->id)
                ->where('token', $validated['token'])
                ->delete();

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'FCM token removed successfully',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'FCM token not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to remove FCM token: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'token' => substr($validated['token'], 0, 20) . '...',
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove FCM token',
            ], 500);
        }
    }
}

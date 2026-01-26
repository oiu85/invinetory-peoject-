<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Services\FcmNotificationService;

class FirebaseDiagnosticController extends Controller
{
    /**
     * Check Firebase configuration and service status.
     * This endpoint helps diagnose Firebase setup issues.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkStatus(Request $request): JsonResponse
    {
        $admin = $request->user();

        if (!$admin || !$admin->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $diagnostics = [
            'credentials_file' => [
                'path' => config('firebase.credentials_path'),
                'exists' => file_exists(config('firebase.credentials_path')),
                'readable' => file_exists(config('firebase.credentials_path')) 
                    ? is_readable(config('firebase.credentials_path')) 
                    : false,
            ],
            'service_initialized' => false,
            'project_id' => null,
            'errors' => [],
        ];

        // Check credentials file
        $credentialsPath = config('firebase.credentials_path');
        
        if (!file_exists($credentialsPath)) {
            $diagnostics['errors'][] = 'Firebase credentials file not found at: ' . $credentialsPath;
            return response()->json([
                'success' => false,
                'message' => 'Firebase credentials file not found',
                'diagnostics' => $diagnostics,
            ], 500);
        }

        if (!is_readable($credentialsPath)) {
            $diagnostics['errors'][] = 'Firebase credentials file is not readable';
            return response()->json([
                'success' => false,
                'message' => 'Firebase credentials file is not readable',
                'diagnostics' => $diagnostics,
            ], 500);
        }

        // Validate JSON
        $credentialsContent = file_get_contents($credentialsPath);
        $credentials = json_decode($credentialsContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $diagnostics['errors'][] = 'Firebase credentials file is not valid JSON: ' . json_last_error_msg();
            return response()->json([
                'success' => false,
                'message' => 'Firebase credentials file is not valid JSON',
                'diagnostics' => $diagnostics,
            ], 500);
        }

        if (isset($credentials['project_id'])) {
            $diagnostics['project_id'] = $credentials['project_id'];
        } else {
            $diagnostics['errors'][] = 'Firebase credentials file is missing project_id';
        }

        // Check if service is initialized
        try {
            $fcmService = app(FcmNotificationService::class);
            
            // Use reflection to check messaging property
            $reflection = new \ReflectionClass($fcmService);
            $messagingProperty = $reflection->getProperty('messaging');
            $messagingProperty->setAccessible(true);
            $messaging = $messagingProperty->getValue($fcmService);
            
            $diagnostics['service_initialized'] = $messaging !== null;
            
            if (!$messaging) {
                $diagnostics['errors'][] = 'Firebase Messaging service is not initialized';
            }
        } catch (\Exception $e) {
            $diagnostics['errors'][] = 'Error checking service: ' . $e->getMessage();
        }

        // Check FCM tokens
        $tokenCount = \App\Models\FcmToken::count();
        
        // Get driver and admin token counts using join
        $driverTokenCount = \App\Models\FcmToken::join('users', 'fcm_tokens.user_id', '=', 'users.id')
            ->where('users.type', 'driver')
            ->count();
        $adminTokenCount = \App\Models\FcmToken::join('users', 'fcm_tokens.user_id', '=', 'users.id')
            ->where('users.type', 'admin')
            ->count();
        
        // Get specific driver token count for debugging
        $specificDriverTokens = \App\Models\FcmToken::where('user_id', 2)->count();

        $diagnostics['tokens'] = [
            'total' => $tokenCount,
            'drivers' => $driverTokenCount,
            'admins' => $adminTokenCount,
            'driver_id_2' => $specificDriverTokens,
        ];

        $hasErrors = !empty($diagnostics['errors']) || !$diagnostics['service_initialized'];

        return response()->json([
            'success' => !$hasErrors,
            'message' => $hasErrors 
                ? 'Firebase configuration has issues' 
                : 'Firebase is properly configured',
            'diagnostics' => $diagnostics,
        ], $hasErrors ? 500 : 200);
    }
}

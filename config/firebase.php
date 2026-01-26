<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Firebase Cloud Messaging (FCM) service.
    | The credentials_path should point to your Firebase service account JSON file.
    |
    */

    'credentials_path' => env('FIREBASE_CREDENTIALS_PATH', storage_path('app/wearehousex-35d78-firebase-adminsdk-fbsvc-58ee25881a.json')),
];

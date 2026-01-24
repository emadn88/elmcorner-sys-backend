<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Firebase Cloud Messaging (FCM) push notifications.
    |
    */

    'project_id' => env('FIREBASE_PROJECT_ID', 'elmcorner-management'),

    'credentials_path' => env(
        'FIREBASE_CREDENTIALS',
        storage_path('app/firebase/service-account.json')
    ),

    /*
    |--------------------------------------------------------------------------
    | FCM Settings
    |--------------------------------------------------------------------------
    */

    'fcm' => [
        'timeout' => env('FIREBASE_FCM_TIMEOUT', 30),
    ],
];

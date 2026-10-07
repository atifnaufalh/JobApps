<?php

return [
    'mailtarget' => [
        'key' => env('MAILTARGET_API_KEY'),
        'endpoint' => env('MAILTARGET_ENDPOINT', 'https://transmission.mailtarget.co/v1/layang/transmissions'),
    ],
    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'api_key' => env('FIREBASE_API_KEY'),
        'auth_domain' => env('FIREBASE_AUTH_DOMAIN'),
        'storage_bucket' => env('FIREBASE_STORAGE_BUCKET'),
        'messaging_sender_id' => env('FIREBASE_MESSAGING_SENDER_ID'),
        'app_id' => env('FIREBASE_APP_ID'),
        'measurement_id' => env('FIREBASE_MEASUREMENT_ID'),
        'service_account_json' => env('FIREBASE_SERVICE_ACCOUNT_JSON'),
    ],
    'otp' => [
        'secret' => env('OTP_SECRET'),
    ],
];

<?php

declare(strict_types=1);

return [
    'env' => (string) env('APP_ENV', 'local'),
    'debug' => (bool) env('APP_DEBUG', true),
    'base_path' => rtrim((string) env('APP_BASE_PATH', ''), '/'),
    'timezone' => (string) env('APP_TIMEZONE', 'Asia/Dhaka'),
    'demo_mode' => (bool) env('APP_DEMO_MODE', true),
    'default_lang' => 'bn',
    'languages' => ['bn', 'en'],
    'asset_version' => '1.0.0',
    'session' => [
        'name' => (string) env('SESSION_NAME', 'dhakahotelsessid'),
        'lifetime' => (int) env('SESSION_LIFETIME', 7200),
        'secure' => env('SESSION_SECURE', 'auto'),
        'samesite' => (string) env('SESSION_SAMESITE', 'Lax'),
    ],
    'owner' => [
        // Fallbacks only: values stored in restaurant_settings take priority.
        'email' => (string) env('OWNER_EMAIL', ''),
        'whatsapp' => (string) env('OWNER_WHATSAPP', ''),
    ],
    'mail' => [
        'host' => (string) env('MAIL_HOST', ''),
        'port' => (int) env('MAIL_PORT', 587),
        'username' => (string) env('MAIL_USERNAME', ''),
        'password' => (string) env('MAIL_PASSWORD', ''),
        'encryption' => (string) env('MAIL_ENCRYPTION', 'tls'),
        'from_address' => (string) env('MAIL_FROM_ADDRESS', ''),
        'from_name' => (string) env('MAIL_FROM_NAME', 'Dhaka Hotel'),
    ],
    'demo_reset_allowed' => (bool) env('DEMO_RESET_ALLOWED', false),
];

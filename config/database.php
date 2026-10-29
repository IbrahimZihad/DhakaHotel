<?php

declare(strict_types=1);

return [
    'host' => (string) env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', 3306),
    'name' => (string) env('DB_NAME', 'dhaka_hotel_demo'),
    'user' => (string) env('DB_USER', 'root'),
    'password' => (string) env('DB_PASSWORD', ''),
    'charset' => (string) env('DB_CHARSET', 'utf8mb4'),
    'ssl_ca' => (string) env('DB_SSL_CA', ''),
];

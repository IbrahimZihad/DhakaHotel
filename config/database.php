<?php

declare(strict_types=1);

return [
    'host' => (string) env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', 3306),
    'name' => (string) env('DB_NAME', 'dhaka_hotel_demo'),
    'user' => (string) env('DB_USER', 'root'),
    'password' => (string) env('DB_PASSWORD', ''),
    'charset' => (string) env('DB_CHARSET', 'utf8mb4'),
    // '', 'required' or 'disabled'. Managed databases such as Aiven need
    // 'required', which also refuses a downgrade to plaintext.
    'ssl_mode' => strtolower(trim((string) env('DB_SSL_MODE', ''))),
    'ssl_ca' => (string) env('DB_SSL_CA', ''),
    // Set false only as a stopgap: the link stays encrypted but the server
    // certificate is no longer checked against the CA.
    'ssl_verify_server_cert' => (bool) env('DB_SSL_VERIFY_SERVER_CERT', true),
];

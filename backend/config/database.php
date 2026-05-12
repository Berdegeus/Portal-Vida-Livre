<?php

declare(strict_types=1);

return [
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'portal_vida_livre'),
    'username' => env('DB_USERNAME', 'portal_app_runtime'),
    'password' => secret_value('DB_PASSWORD', ''),
    'public_username' => env('DB_PUBLIC_USERNAME', 'portal_public_reader'),
    'public_password' => secret_value('DB_PUBLIC_PASSWORD', ''),
    'admin_username' => env('DB_ADMIN_USERNAME', 'portal_admin_runtime'),
    'admin_password' => secret_value('DB_ADMIN_PASSWORD', ''),
    'report_username' => env('DB_REPORT_USERNAME', 'portal_report_reader'),
    'report_password' => secret_value('DB_REPORT_PASSWORD', ''),
    'maintenance_username' => env('DB_MAINTENANCE_USERNAME', 'root'),
    'maintenance_password' => secret_value('DB_MAINTENANCE_PASSWORD', secret_value('DB_PASSWORD', '')),
    'charset' => env('DB_CHARSET', 'utf8mb4'),
    'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
];

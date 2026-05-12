<?php

declare(strict_types=1);

return [
    'host' => env('SMTP_HOST', ''),
    'port' => (int) env('SMTP_PORT', '587'),
    'encryption' => env('SMTP_ENCRYPTION', 'tls'),
    'username' => env('SMTP_USERNAME', ''),
    'password' => secret_value('SMTP_PASSWORD', ''),
    'from_email' => env('SMTP_FROM_EMAIL', ''),
    'from_name' => env('SMTP_FROM_NAME', 'Portal Vida Livre'),
];


<?php

declare(strict_types=1);

return [
    'required_local' => [
        'APP_KEY',
        'DB_PASSWORD',
        'DB_PUBLIC_PASSWORD',
        'DB_ADMIN_PASSWORD',
        'DB_REPORT_PASSWORD',
    ],
    'obfuscatable' => [
        'APP_KEY',
        'DB_PASSWORD',
        'DB_PUBLIC_PASSWORD',
        'DB_ADMIN_PASSWORD',
        'DB_REPORT_PASSWORD',
        'DB_MAINTENANCE_PASSWORD',
        'SMTP_PASSWORD',
        'TELEGRAM_BOT_TOKEN',
        'ADMIN_SECURITY_ANSWER_1',
        'ADMIN_SECURITY_ANSWER_2',
        'ADMIN_SECURITY_ANSWER_3',
    ],
    'placeholder_markers' => [
        'troque',
        'senha-gerada',
        'senha-root',
        'placeholder',
        'sua-senha',
        'seu-email',
    ],
];

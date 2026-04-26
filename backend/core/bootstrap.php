<?php

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/directory.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/totp.php';
require_once __DIR__ . '/telegram.php';

load_env(dirname(__DIR__) . '/.env');

ensure_session_started();

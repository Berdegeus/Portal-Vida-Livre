<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Use este arquivo apenas pela CLI.');
}

require_once __DIR__ . '/backend/core/env.php';
require_once __DIR__ . '/backend/core/helpers.php';
require_once __DIR__ . '/backend/core/db.php';
require_once __DIR__ . '/backend/core/schema.php';
require_once __DIR__ . '/backend/core/seed.php';

load_env(__DIR__ . '/backend/.env');
run_schema();
run_seed();

$host = env('APP_HOST', 'localhost');
$port = env('APP_PORT', '8000');
$command = sprintf(
    '%s -S %s -t %s',
    escapeshellarg(PHP_BINARY),
    escapeshellarg($host . ':' . $port),
    escapeshellarg(__DIR__)
);

passthru($command, $exitCode);
exit((int) $exitCode);

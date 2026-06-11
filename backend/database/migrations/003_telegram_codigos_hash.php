<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

chdir(dirname(__DIR__, 3));

require_once __DIR__ . '/../../core/env.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/secrets.php';
require_once __DIR__ . '/../../core/db.php';

load_env(__DIR__ . '/../../.env');

function migration_pdo(): \PDO
{
    return maintenance_db();
}

function migration_up(): void
{
    $pdo = migration_pdo();

    $pdo->exec('TRUNCATE TABLE telegram_codigos');

    $pdo->exec("ALTER TABLE telegram_codigos
        ADD COLUMN codigo_hash CHAR(64) NOT NULL AFTER admin_id,
        DROP COLUMN codigo");

    echo "[003] UP aplicado: telegram_codigos migrado para codigo_hash.\n";
}

function migration_down(): void
{
    $pdo = migration_pdo();

    $pdo->exec('TRUNCATE TABLE telegram_codigos');

    $pdo->exec("ALTER TABLE telegram_codigos
        ADD COLUMN codigo VARCHAR(6) NOT NULL AFTER admin_id,
        DROP COLUMN codigo_hash");

    echo "[003] DOWN aplicado: telegram_codigos revertido para codigo plaintext.\n";
}

$action = $argv[1] ?? '';

if ($action === 'up') {
    migration_up();
} elseif ($action === 'down') {
    migration_down();
} else {
    fwrite(STDERR, "Uso: php " . basename(__FILE__) . " up|down\n");
    exit(1);
}

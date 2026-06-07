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
    migration_pdo()->exec(
        "ALTER TABLE user_telegram_codigos
         MODIFY COLUMN tipo ENUM('vinculacao', 'reset_senha', 'login') NOT NULL DEFAULT 'vinculacao'"
    );

    echo "[002] UP aplicado: tipo 'login' adicionado ao ENUM de user_telegram_codigos.\n";
}

function migration_down(): void
{
    $pdo = migration_pdo();

    $pdo->exec("DELETE FROM user_telegram_codigos WHERE tipo = 'login'");

    $pdo->exec(
        "ALTER TABLE user_telegram_codigos
         MODIFY COLUMN tipo ENUM('vinculacao', 'reset_senha') NOT NULL DEFAULT 'vinculacao'"
    );

    echo "[002] DOWN aplicado: tipo 'login' removido do ENUM de user_telegram_codigos.\n";
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

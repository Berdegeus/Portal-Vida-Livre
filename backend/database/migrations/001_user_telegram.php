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

    $pdo->exec("
        ALTER TABLE users
            ADD COLUMN IF NOT EXISTS telegram_chat_id BIGINT NULL DEFAULT NULL AFTER updated_at
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_telegram_codigos (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id    BIGINT UNSIGNED NOT NULL,
            codigo     VARCHAR(10) NOT NULL,
            tipo       ENUM('vinculacao', 'reset_senha') NOT NULL DEFAULT 'vinculacao',
            extra_data VARCHAR(255) NULL,
            criado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            usado      TINYINT(1) NOT NULL DEFAULT 0,
            KEY idx_utc_user_tipo (user_id, tipo),
            CONSTRAINT fk_utc_user
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "[001] UP aplicado: users.telegram_chat_id e tabela user_telegram_codigos criados.\n";
}

function migration_down(): void
{
    $pdo = migration_pdo();

    $pdo->exec('DROP TABLE IF EXISTS user_telegram_codigos');

    $pdo->exec("
        ALTER TABLE users DROP COLUMN IF EXISTS telegram_chat_id
    ");

    echo "[001] DOWN aplicado: tabela user_telegram_codigos removida e coluna telegram_chat_id revertida.\n";
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

<?php

declare(strict_types=1);

use OTPHP\TOTP;

// ─── Geração e verificação TOTP ──────────────────────────────────────────────

function totp_generate_secret(): string
{
    return TOTP::generate()->getSecret();
}

function verify_totp_code(string $secret, string $code): bool
{
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }

    $totp = TOTP::createFromSecret($secret);

    // Janela de 1 permite 30s de tolerância para frente e para trás
    return $totp->verify($code, null, 1);
}

function build_otpauth_uri(string $email, string $secret): string
{
    $totp = TOTP::createFromSecret($secret);
    $totp->setIssuer('Portal Vida Livre');
    $totp->setLabel($email);

    return $totp->getProvisioningUri();
}

function build_qr_code_url(string $otpauthUri): string
{
    return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='
        . urlencode($otpauthUri);
}

// ─── Setup pendente (antes de confirmar) ─────────────────────────────────────

function store_pending_two_factor_secret(int $userId, string $secret): void
{
    $encrypted = encrypt_sensitive_value($secret);

    $statement = db()->prepare(
        'UPDATE users
         SET two_factor_temp_secret_encrypted = :secret,
             two_factor_temp_secret_created_at = NOW(),
             updated_at = NOW()
         WHERE id = :id'
    );
    $statement->execute([
        'secret' => $encrypted,
        'id'     => $userId,
    ]);
}

function get_pending_two_factor_secret(array $user): ?string
{
    $encrypted = $user['two_factor_temp_secret_encrypted'] ?? null;

    if (empty($encrypted)) {
        return null;
    }

    return decrypt_sensitive_value((string) $encrypted);
}

// ─── Ativação ────────────────────────────────────────────────────────────────

function enable_two_factor_for_user(int $userId, string $encryptedTempSecret): void
{
    $statement = db()->prepare(
        'UPDATE users
         SET two_factor_enabled = 1,
             two_factor_secret_encrypted = :secret,
             two_factor_confirmed_at = NOW(),
             two_factor_temp_secret_encrypted = NULL,
             two_factor_temp_secret_created_at = NULL,
             updated_at = NOW()
         WHERE id = :id'
    );
    $statement->execute([
        'secret' => $encryptedTempSecret,
        'id'     => $userId,
    ]);
}

// ─── Desativação ─────────────────────────────────────────────────────────────

function disable_two_factor_for_user(int $userId): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $pdo->prepare(
            'UPDATE users
             SET two_factor_enabled = 0,
                 two_factor_secret_encrypted = NULL,
                 two_factor_confirmed_at = NULL,
                 two_factor_temp_secret_encrypted = NULL,
                 two_factor_temp_secret_created_at = NULL,
                 updated_at = NOW()
             WHERE id = :id'
        )->execute(['id' => $userId]);

        $pdo->prepare(
            'DELETE FROM user_backup_codes WHERE user_id = :user_id'
        )->execute(['user_id' => $userId]);

        $pdo->commit();
    } catch (\Throwable $throwable) {
        $pdo->rollBack();
        throw $throwable;
    }
}

// ─── Backup codes ─────────────────────────────────────────────────────────────

function generate_backup_codes(int $count = 8): array
{
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        // Formato legível: xxxxxxxx-xxxxxxxx
        $codes[] = bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(4));
    }
    return $codes;
}

function replace_backup_codes(int $userId): array
{
    $codes = generate_backup_codes();
    $pdo   = db();

    $pdo->beginTransaction();

    try {
        $pdo->prepare(
            'DELETE FROM user_backup_codes WHERE user_id = :user_id'
        )->execute(['user_id' => $userId]);

        $insert = $pdo->prepare(
            'INSERT INTO user_backup_codes (user_id, code_hash)
             VALUES (:user_id, :code_hash)'
        );

        foreach ($codes as $code) {
            $insert->execute([
                'user_id'   => $userId,
                'code_hash' => password_hash($code, PASSWORD_DEFAULT),
            ]);
        }

        $pdo->commit();
    } catch (\Throwable $throwable) {
        $pdo->rollBack();
        throw $throwable;
    }

    // Retorna em texto puro só desta vez para exibir ao usuário
    return $codes;
}

function verify_and_consume_backup_code(int $userId, string $code): bool
{
    $statement = db()->prepare(
        'SELECT id, code_hash
         FROM user_backup_codes
         WHERE user_id = :user_id AND used_at IS NULL'
    );
    $statement->execute(['user_id' => $userId]);
    $rows = $statement->fetchAll();

    foreach ($rows as $row) {
        if (password_verify($code, (string) $row['code_hash'])) {
            db()->prepare(
                'UPDATE user_backup_codes
                 SET used_at = NOW()
                 WHERE id = :id'
            )->execute(['id' => $row['id']]);

            return true;
        }
    }

    return false;
}

function count_remaining_backup_codes(int $userId): int
{
    $statement = db()->prepare(
        'SELECT COUNT(*) FROM user_backup_codes
         WHERE user_id = :user_id AND used_at IS NULL'
    );
    $statement->execute(['user_id' => $userId]);

    return (int) $statement->fetchColumn();
}
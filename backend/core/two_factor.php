<?php

declare(strict_types=1);

function generate_totp_secret(int $byteLength = 20): string
{
    return base32_encode(random_bytes($byteLength));
}

function base32_encode(string $data): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $binary = '';
    $encoded = '';

    foreach (str_split($data) as $character) {
        $binary .= str_pad(decbin(ord($character)), 8, '0', STR_PAD_LEFT);
    }

    $chunks = str_split($binary, 5);

    foreach ($chunks as $chunk) {
        $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        $encoded .= $alphabet[bindec($chunk)];
    }

    return $encoded;
}

function base32_decode(string $input): string
{
    $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
    $clean = strtoupper(preg_replace('/[^A-Z2-7]/', '', $input) ?? '');
    $binary = '';
    $decoded = '';

    foreach (str_split($clean) as $character) {
        if (!isset($alphabet[$character])) {
            throw new \RuntimeException('Segredo TOTP invalido.');
        }

        $binary .= str_pad(decbin($alphabet[$character]), 5, '0', STR_PAD_LEFT);
    }

    $bytes = str_split($binary, 8);

    foreach ($bytes as $byte) {
        if (strlen($byte) === 8) {
            $decoded .= chr(bindec($byte));
        }
    }

    return $decoded;
}

function totp_pack_counter(int $counter): string
{
    $binary = '';

    for ($position = 7; $position >= 0; $position--) {
        $binary .= chr(($counter >> ($position * 8)) & 0xFF);
    }

    return $binary;
}

function generate_totp_code(string $secret, int $counter, int $digits = 6): string
{
    $key = base32_decode($secret);
    $hash = hash_hmac('sha1', totp_pack_counter($counter), $key, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $segment = substr($hash, $offset, 4);
    $value = unpack('N', $segment)[1] & 0x7FFFFFFF;
    $modulo = 10 ** $digits;

    return str_pad((string) ($value % $modulo), $digits, '0', STR_PAD_LEFT);
}

function verify_totp_code(string $secret, string $code, int $window = 1): bool
{
    $normalized = preg_replace('/\D/', '', $code) ?? '';

    if (strlen($normalized) !== 6) {
        return false;
    }

    $counter = (int) floor(time() / 30);

    for ($offset = -$window; $offset <= $window; $offset++) {
        $candidate = generate_totp_code($secret, $counter + $offset);

        if (hash_equals($candidate, $normalized)) {
            return true;
        }
    }

    return false;
}

function build_otpauth_uri(string $email, string $secret): string
{
    $issuer = 'Portal Vida Livre';
    $label = rawurlencode($issuer . ':' . $email);

    return 'otpauth://totp/' . $label
        . '?secret=' . rawurlencode($secret)
        . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=6&period=30';
}

function render_two_factor_qr_code(string $otpauthUri): string
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';

    if (!file_exists($autoload)) {
        throw new \RuntimeException('Dependencias do backend nao instaladas.');
    }

    require_once $autoload;

    return (new \chillerlan\QRCode\QRCode())->render($otpauthUri);
}

function generate_backup_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $first = '';
    $second = '';

    for ($index = 0; $index < 4; $index++) {
        $first .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $second .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    return $first . '-' . $second;
}

function normalize_backup_code(string $code): string
{
    $code = strtoupper(trim($code));

    return preg_replace('/[^A-Z0-9]/', '', $code) ?? '';
}

function count_backup_codes_remaining(int $userId): int
{
    $statement = db()->prepare(
        'SELECT COUNT(*) FROM user_backup_codes WHERE user_id = :user_id AND used_at IS NULL'
    );
    $statement->execute(['user_id' => $userId]);

    return (int) $statement->fetchColumn();
}

function replace_backup_codes(int $userId, int $count = 8): array
{
    $codes = [];
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $deleteStatement = $pdo->prepare('DELETE FROM user_backup_codes WHERE user_id = :user_id');
        $deleteStatement->execute(['user_id' => $userId]);

        $insertStatement = $pdo->prepare(
            'INSERT INTO user_backup_codes (user_id, code_hash) VALUES (:user_id, :code_hash)'
        );

        for ($index = 0; $index < $count; $index++) {
            $plainCode = generate_backup_code();
            $codes[] = $plainCode;

            $insertStatement->execute([
                'user_id' => $userId,
                'code_hash' => password_hash(normalize_backup_code($plainCode), PASSWORD_DEFAULT),
            ]);
        }

        $pdo->commit();
    } catch (\Throwable $throwable) {
        $pdo->rollBack();
        throw $throwable;
    }

    return $codes;
}

function verify_and_consume_backup_code(int $userId, string $input): bool
{
    $normalized = normalize_backup_code($input);

    if ($normalized === '') {
        return false;
    }

    $statement = db()->prepare(
        'SELECT id, code_hash
         FROM user_backup_codes
         WHERE user_id = :user_id
           AND used_at IS NULL'
    );
    $statement->execute(['user_id' => $userId]);
    $codes = $statement->fetchAll();

    foreach ($codes as $code) {
        if (password_verify($normalized, (string) $code['code_hash'])) {
            $updateStatement = db()->prepare(
                'UPDATE user_backup_codes SET used_at = NOW() WHERE id = :id'
            );
            $updateStatement->execute(['id' => (int) $code['id']]);

            return true;
        }
    }

    return false;
}

function store_pending_two_factor_secret(int $userId, string $encryptedSecret): void
{
    $statement = db()->prepare(
        'UPDATE users
         SET two_factor_temp_secret_encrypted = :secret,
             two_factor_temp_secret_created_at = NOW()
         WHERE id = :id'
    );
    $statement->execute([
        'secret' => $encryptedSecret,
        'id' => $userId,
    ]);
}

function enable_two_factor_for_user(int $userId, string $encryptedSecret): void
{
    $statement = db()->prepare(
        'UPDATE users
         SET two_factor_enabled = 1,
             two_factor_secret_encrypted = :secret,
             two_factor_temp_secret_encrypted = NULL,
             two_factor_temp_secret_created_at = NULL,
             two_factor_confirmed_at = NOW()
         WHERE id = :id'
    );
    $statement->execute([
        'secret' => $encryptedSecret,
        'id' => $userId,
    ]);
}

function disable_two_factor_for_user(int $userId): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $updateStatement = $pdo->prepare(
            'UPDATE users
             SET two_factor_enabled = 0,
                 two_factor_secret_encrypted = NULL,
                 two_factor_temp_secret_encrypted = NULL,
                 two_factor_temp_secret_created_at = NULL,
                 two_factor_confirmed_at = NULL
             WHERE id = :id'
        );
        $updateStatement->execute(['id' => $userId]);

        $deleteStatement = $pdo->prepare('DELETE FROM user_backup_codes WHERE user_id = :user_id');
        $deleteStatement->execute(['user_id' => $userId]);

        $pdo->commit();
    } catch (\Throwable $throwable) {
        $pdo->rollBack();
        throw $throwable;
    }
}

function two_factor_status_array(array $user): array
{
    return [
        'enabled' => (bool) ($user['two_factor_enabled'] ?? false),
        'confirmed_at' => $user['two_factor_confirmed_at'] ?? null,
        'setup_pending' => !empty($user['two_factor_temp_secret_encrypted']),
        'backup_codes_remaining' => count_backup_codes_remaining((int) $user['id']),
    ];
}


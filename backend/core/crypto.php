<?php

declare(strict_types=1);

function load_rsa_private_key(): \OpenSSLAsymmetricKey
{
    $pem = base64_decode(required_secret('RSA_PRIVATE_KEY'));
    $key = openssl_pkey_get_private($pem);

    if ($key === false) {
        throw new \RuntimeException('Nao foi possivel carregar a chave privada RSA.');
    }

    return $key;
}

function app_key_bytes(): string
{
    $key = required_secret('APP_KEY');

    if (str_starts_with($key, 'base64:')) {
        $decoded = base64_decode(substr($key, 7), true);

        if ($decoded === false || strlen($decoded) < 32) {
            throw new \RuntimeException('APP_KEY invalida.');
        }

        return substr($decoded, 0, 32);
    }

    return hash('sha256', $key, true);
}

function encrypt_sensitive_value(string $value): string
{
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt(
        $value,
        'aes-256-gcm',
        app_key_bytes(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if (!is_string($ciphertext)) {
        throw new \RuntimeException('Nao foi possivel criptografar o valor.');
    }

    return base64_encode($iv . $tag . $ciphertext);
}

function decrypt_sensitive_value(string $payload, string $field = 'unknown', int|string|null $actorId = null): string
{
    $decoded = base64_decode($payload, true);

    if ($decoded === false || strlen($decoded) < 29) {
        throw new \RuntimeException('Valor criptografado invalido.');
    }

    $iv = substr($decoded, 0, 12);
    $tag = substr($decoded, 12, 16);
    $ciphertext = substr($decoded, 28);

    $plaintext = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        app_key_bytes(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if (!is_string($plaintext)) {
        throw new \RuntimeException('Nao foi possivel descriptografar o valor.');
    }

    $host    = gethostname() ?: 'unknown';
    $actorId = $actorId !== null ? (string) $actorId : 'system';
    error_log("{$actorId}:{$host}>campo={$field} descriptografado user_id={$actorId}");

    return $plaintext;
}


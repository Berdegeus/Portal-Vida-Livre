<?php

declare(strict_types=1);

function app_key_bytes(): string
{
    $key = env('APP_KEY');

    if (!is_string($key) || $key === '') {
        throw new \RuntimeException('APP_KEY nao configurada.');
    }

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

function decrypt_sensitive_value(string $payload): string
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

    return $plaintext;
}


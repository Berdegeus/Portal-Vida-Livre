<?php

declare(strict_types=1);

function hybrid_decrypt_payload(array $raw): array
{
    $sessionKeyEnc = base64_decode((string) ($raw['session_key_encrypted'] ?? ''), true);
    $dataEnc       = base64_decode((string) ($raw['data_encrypted'] ?? ''), true);
    $iv            = base64_decode((string) ($raw['iv'] ?? ''), true);

    if ($sessionKeyEnc === false || $dataEnc === false || $iv === false) {
        return $raw;
    }

    $privateKeyPem = base64_decode(required_secret('RSA_PRIVATE_KEY'));
    $privateKey    = openssl_pkey_get_private($privateKeyPem);
    if ($privateKey === false) {
        return $raw;
    }

    $sessionKey = '';
    if (!openssl_private_decrypt($sessionKeyEnc, $sessionKey, $privateKey, OPENSSL_PKCS1_OAEP_PADDING)) {
        return $raw;
    }

    // Web Crypto AES-GCM output = ciphertext || tag (last 16 bytes)
    $tag        = substr($dataEnc, -16);
    $ciphertext = substr($dataEnc, 0, -16);

    $decrypted = openssl_decrypt($ciphertext, 'aes-256-gcm', $sessionKey, OPENSSL_RAW_DATA, $iv, $tag);
    if ($decrypted === false) {
        return $raw;
    }

    $decoded = json_decode($decrypted, true);
    if (!is_array($decoded)) {
        return $raw;
    }

    $usuario  = (string) ($_SESSION['user_id'] ?? ($_SESSION['admin_id'] ?? 'anonimo'));
    $hostname = (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido');
    error_log("{$usuario}:{$hostname}>" . $decrypted);

    return $decoded;
}

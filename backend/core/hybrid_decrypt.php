<?php

declare(strict_types=1);

function hybrid_decrypt_payload(array $raw): array
{
    static $privateKey = null;

    $sessionKeyEnc = base64_decode((string) ($raw['session_key_encrypted'] ?? ''), true);
    $dataEnc       = base64_decode((string) ($raw['data_encrypted'] ?? ''), true);
    $iv            = base64_decode((string) ($raw['iv'] ?? ''), true);

    if ($sessionKeyEnc === false || $dataEnc === false || $iv === false) {
        throw new \RuntimeException('[HybridCrypto] base64 decode failed — payload malformed');
    }

    if (strlen($iv) !== 12) {
        throw new \RuntimeException('[HybridCrypto] IV length invalid: ' . strlen($iv) . ' bytes (expected 12)');
    }

    if ($privateKey === null) {
        $privateKeyPem = base64_decode(required_secret('RSA_PRIVATE_KEY'));
        $privateKey    = openssl_pkey_get_private($privateKeyPem);
    }

    if ($privateKey === false) {
        throw new \RuntimeException('[HybridCrypto] failed to load RSA private key');
    }

    $sessionKey = '';
    if (!openssl_private_decrypt($sessionKeyEnc, $sessionKey, $privateKey, OPENSSL_PKCS1_OAEP_PADDING)) {
        throw new \RuntimeException('[HybridCrypto] RSA-OAEP decryption failed');
    }

    // Web Crypto AES-GCM output = ciphertext || tag (last 16 bytes)
    $tag        = substr($dataEnc, -16);
    $ciphertext = substr($dataEnc, 0, -16);

    $decrypted = openssl_decrypt($ciphertext, 'aes-256-gcm', $sessionKey, OPENSSL_RAW_DATA, $iv, $tag);
    if ($decrypted === false) {
        throw new \RuntimeException('[HybridCrypto] AES-256-GCM decryption failed');
    }

    $decoded = json_decode($decrypted, true);
    if (!is_array($decoded)) {
        throw new \RuntimeException('[HybridCrypto] decrypted payload is not a JSON object');
    }

    $usuario  = (string) ($_SESSION['user_id'] ?? ($_SESSION['admin_id'] ?? 'anonimo'));
    $hostname = gethostname() ?: 'unknown';
    $fields   = implode(',', array_keys($decoded));
    error_log("{$usuario}:{$hostname}>hybrid_decrypt fields={$fields} count=" . count($decoded));

    return $decoded;
}

<?php

declare(strict_types=1);

function hybrid_decrypt_payload(array $raw): array
{
    static $privateKey = null;

    try {
        $sessionKeyEnc = base64_decode((string) ($raw['session_key_encrypted'] ?? ''), true);
        $dataEnc       = base64_decode((string) ($raw['data_encrypted'] ?? ''), true);
        $iv            = base64_decode((string) ($raw['iv'] ?? ''), true);

        if ($sessionKeyEnc === false || $dataEnc === false || $iv === false) {
            error_log('[HybridCrypto] base64 decode failed — payload malformed or not encrypted');
            return $raw;
        }

        if (strlen($iv) !== 12) {
            error_log('[HybridCrypto] IV length invalid: ' . strlen($iv) . ' bytes (expected 12)');
            return $raw;
        }

        if ($privateKey === null) {
            $privateKeyPem = base64_decode(required_secret('RSA_PRIVATE_KEY'));
            $privateKey    = openssl_pkey_get_private($privateKeyPem);
        }

        if ($privateKey === false) {
            error_log('[HybridCrypto] failed to load RSA private key');
            $privateKey = null;
            return $raw;
        }

        $sessionKey = '';
        if (!openssl_private_decrypt($sessionKeyEnc, $sessionKey, $privateKey, OPENSSL_PKCS1_OAEP_PADDING)) {
            error_log('[HybridCrypto] RSA-OAEP decryption failed — wrong key or corrupted session_key_encrypted');
            return $raw;
        }

        // Web Crypto AES-GCM output = ciphertext || tag (last 16 bytes)
        $tag        = substr($dataEnc, -16);
        $ciphertext = substr($dataEnc, 0, -16);

        $decrypted = openssl_decrypt($ciphertext, 'aes-256-gcm', $sessionKey, OPENSSL_RAW_DATA, $iv, $tag);
        if ($decrypted === false) {
            error_log('[HybridCrypto] AES-256-GCM decryption failed — corrupted ciphertext or tag mismatch');
            return $raw;
        }

        $decoded = json_decode($decrypted, true);
        if (!is_array($decoded)) {
            error_log('[HybridCrypto] decrypted payload is not a JSON object');
            return $raw;
        }

        $usuario  = (string) ($_SESSION['user_id'] ?? ($_SESSION['admin_id'] ?? 'anonimo'));
        $hostname = (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido');
        error_log("{$usuario}:{$hostname}>" . $decrypted);

        return $decoded;
    } catch (\Throwable $e) {
        error_log('[HybridCrypto] unexpected error: ' . $e->getMessage());
        return $raw;
    }
}

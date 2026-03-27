<?php

declare(strict_types=1);
use OTPHP\TOTP;

/* gera nova chave aleatória e retorna o TOTP correspondente */
function totp_create(): TOTP
{
    $totp = TOTP::generate();
    $totp->setIssuer('Portal Vida Livre');

    return $totp;
}

/* recria o TOTP a partir de uma chave secreta já existente no banco */
function totp_from_secret(string $secret): TOTP
{
    return TOTP::createFromSecret($secret);
}

/*verifica se o código do usuário é válido */
function totp_verify(string $secret, string $code): bool
{
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }
    return totp_from_secret($secret)->verify($code, null, 1);
}

/* gera a URL do QR code para o app de autenticação */
function totp_otpauth_url(string $secret, string $email): string
{
    $totp = totp_from_secret($secret);
    $totp->setIssuer('Portal Vida Livre');
    $totp->setLabel($email);

    return $totp->getProvisioningUri();
}

/* nova chave aleatória em base32 para armazenar no banco */
function totp_generate_secret(): string
{
    return totp_create()->getSecret();
}

/*banco tem uma coluna 'totp_secret' que armazena a chave secreta do usuário.
Se for null, o TOTP não ta habilitado */
function find_totp_secret(int $userId): ?array
{
    $statement = db()->prepare(
        'SELECT id, user_id, secret, verified_at
        FROM totp_secrets
        WHERE user_id = :user_id
        LIMIT 1'
    );
    $statement->execute(['user_id' => $userId]);
    $record = $statement->fetch();

    return is_array($record) ? $record : null;
}

function user_has_totp_enabled(int $userId): bool
{
    $record = find_totp_secret($userId);

    return $record !== null && $record['verified_at'] !== null;
}

function save_totp_secret(int $userId, string $secret): void
{
    $statement = db()->prepare(
        'INSERT INTO totp_secrets (user_id, secret, verified_at)
        VALUES (:user_id, :secret, NULL)
        ON DUPLICATE KEY UPDATE secret = :secret, verified_at = NULL'
    );
    $statement->execute([
        'user_id' => $userId,
        'secret'  => $secret,
    ]);
}

function activate_totp(int $userId): void
{
    $statement = db()->prepare(
        'UPDATE totp_secrets
        SET verified_at = NOW()
        WHERE user_id = :user_id'
    );
    $statement->execute(['user_id' => $userId]);
}

function remove_totp(int $userId): void
{
    $statement = db()->prepare(
        'DELETE FROM totp_secrets WHERE user_id = :user_id'
    );
    $statement->execute(['user_id' => $userId]);
}
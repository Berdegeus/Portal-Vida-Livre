<?php

declare(strict_types=1);

function notify_user_verification(array $user, string $token, string $codigo): array
{
    $verifyUrl  = frontend_url('confirmar-email.html?token=' . urlencode($token));
    $botUsername = telegram_bot_username();
    $userCode   = create_user_telegram_codigo((int) $user['id'], 'vinculacao');
    $telegramUrl = 'https://t.me/' . $botUsername . '?start=V_' . $userCode;

    return [
        'channel'       => 'telegram',
        'telegram_url'  => $telegramUrl,
        'bot_username'  => $botUsername,
        'fallback_code' => $codigo,
        'verify_url'    => $verifyUrl,
        'vinc_code'     => 'V_' . $userCode,
    ];
}

function notify_user_password_reset(array $user, string $token): array
{
    $resetUrl    = frontend_url('redefinir-senha.html?token=' . urlencode($token));
    $botUsername = telegram_bot_username();
    $chatId      = isset($user['telegram_chat_id']) ? (int) $user['telegram_chat_id'] : 0;

    if ($chatId !== 0) {
        try {
            telegram_send_message($chatId, "Redefinição de senha — Portal Vida Livre\n\nClique no link abaixo para criar uma nova senha:\n{$resetUrl}\n\nO link expira em 1 hora.");
            return ['channel' => 'telegram_sent'];
        } catch (\Throwable $e) {
            fwrite(STDERR, '[notifier] Erro ao enviar reset via Telegram: ' . $e->getMessage() . "\n");
        }
    }

    $resetCode   = create_user_telegram_codigo((int) $user['id'], 'reset_senha', $token);
    $telegramUrl = 'https://t.me/' . $botUsername . '?start=R_' . $resetCode;

    return [
        'channel'      => 'telegram',
        'telegram_url' => $telegramUrl,
        'bot_username' => $botUsername,
    ];
}

function notify_admin_login(array $admin, string $codigo): bool
{
    $chatId = (int) ($admin['telegram_chat_id'] ?? 0);

    if ($chatId === 0) {
        return false;
    }

    try {
        telegram_send_message($chatId, "Portal Vida Livre — Código de acesso administrativo:\n\n{$codigo}\n\nExpira em 15 minutos. Não compartilhe este código.");
        return true;
    } catch (\Throwable $e) {
        fwrite(STDERR, '[notifier] Erro ao enviar código admin via Telegram: ' . $e->getMessage() . "\n");
        return false;
    }
}

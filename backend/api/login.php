<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();
require_captcha();

$data = request_data();
$email = normalize_email((string) ($data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');
$errors = [];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    add_error($errors, 'email', 'Informe um e-mail valido.');
}

if ($password === '') {
    add_error($errors, 'password', 'Informe sua senha.');
}

if (has_errors($errors)) {
    error_response('Verifique os campos informados.', $errors, 422);
}

$user = find_user_by_email($email);

if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
    log_audit('user.login_failed', [
        'actor_type'  => 'system',
        'actor_email' => $email,
    ]);
    error_response('E-mail ou senha invalidos.', [
        '_general' => ['E-mail ou senha invalidos.'],
    ], 401);
}

if (!user_email_is_verified($user)) {
    $notif = [];

    try {
        $pdo       = db();
        $pdo->beginTransaction();
        $tokenData = store_email_verification_token($pdo, (int) $user['id']);
        $pdo->commit();
        $notif = notify_user_verification($user, $tokenData['token'], $tokenData['codigo']);
    } catch (\Throwable $throwable) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    error_response(
        'Confirme seu cadastro via Telegram. Clique no link enviado para concluir a verificacao.',
        array_merge(['_general' => ['Confirme seu cadastro via Telegram.']], $notif),
        403
    );
}

// Telegram 2FA obrigatório para todos os usuários.
$chatId = find_user_telegram_chat_id((int) $user['id']);

if ($chatId === null) {
    // Sem Telegram vinculado: exigir vinculação antes de permitir login.
    $userCode    = create_user_telegram_codigo((int) $user['id'], 'vinculacao');
    $telegramUrl = 'https://t.me/' . telegram_bot_username() . '?start=V_' . $userCode;
    start_user_telegram_pending((int) $user['id']);

    error_response(
        'Vincule seu Telegram para concluir o login.',
        [
            'step'         => 'vinculacao',
            'telegram_url' => $telegramUrl,
            'bot_username' => telegram_bot_username(),
        ],
        403
    );
}

// Com Telegram vinculado: enviar OTP de 6 dígitos.
$codigo = create_user_telegram_codigo((int) $user['id'], 'login');

try {
    telegram_send_message(
        $chatId,
        "Código de acesso — Portal Vida Livre:\n\n{$codigo}\n\nExpira em 10 minutos. Não compartilhe."
    );
} catch (\Throwable $e) {
    fwrite(STDERR, '[login] Erro ao enviar OTP Telegram: ' . $e->getMessage() . "\n");
    error_response('Nao foi possivel enviar o codigo pelo Telegram. Tente novamente.', [], 503);
}

start_user_telegram_pending((int) $user['id']);

log_audit('user.login_telegram_otp_sent', [
    'actor_type'  => 'user',
    'actor_id'    => $user['id'],
    'actor_email' => $user['email'],
]);

success_response('Codigo enviado ao seu Telegram.', [
    'requires_2fa' => true,
    'step'         => 'telegram_code',
    'csrf_token'   => rotate_csrf_token(),
]);

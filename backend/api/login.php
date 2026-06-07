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

if ((bool) ($user['two_factor_enabled'] ?? false) && !empty($user['two_factor_secret_encrypted'])) {
    start_two_factor_pending((int) $user['id']);

    log_audit('user.login_2fa_required', [
        'actor_type'  => 'user',
        'actor_id'    => $user['id'],
        'actor_email' => $user['email'],
    ]);

    success_response('Codigo de verificacao necessario.', [
        'requires_2fa' => true,
        'csrf_token' => rotate_csrf_token(),
    ]);
}

$publicUser = login_user($user);

log_audit('user.login', [
    'actor_type'  => 'user',
    'actor_id'    => $user['id'],
    'actor_email' => $user['email'],
]);

success_response('Login realizado com sucesso.', [
    'user' => $publicUser,
    'csrf_token' => rotate_csrf_token(),
]);

<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();

$data = request_data();
$email = normalize_email((string) ($data['email'] ?? ''));
$errors = [];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    add_error($errors, 'email', 'Informe um e-mail valido.');
}

if (has_errors($errors)) {
    error_response('Verifique os campos informados.', $errors, 422);
}

$user = find_user_by_email($email);

if ($user !== null) {
    try {
        $token = create_password_reset_token((int) $user['id']);
        send_password_reset_email($user, $token);
    } catch (\Throwable $throwable) {
        error_response('Nao foi possivel processar sua solicitacao agora.', [], 500);
    }

    log_audit('user.password_reset_requested', [
        'actor_type'  => 'user',
        'actor_id'    => $user['id'],
        'actor_email' => $user['email'],
    ]);
}

success_response('Se o e-mail estiver cadastrado, enviaremos um link para redefinicao de senha.');


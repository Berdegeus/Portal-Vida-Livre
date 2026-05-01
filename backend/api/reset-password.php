<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() === 'GET') {
    $token = trim((string) ($_GET['token'] ?? ''));
    $record = find_password_reset_token($token);

    if ($record === null) {
        error_response('O link informado e invalido ou expirou.', [
            'token' => ['O link informado e invalido ou expirou.'],
        ], 410);
    }

    success_response('Token valido.', [
        'valid' => true,
    ]);
}

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();

$data = request_data();
$token = trim((string) ($data['token'] ?? ''));
$password = (string) ($data['password'] ?? '');
$passwordConfirmation = (string) ($data['password_confirmation'] ?? '');
$errors = [];
$record = find_password_reset_token($token);

if ($record === null) {
    add_error($errors, 'token', 'O link informado e invalido ou expirou.');
}

foreach (password_strength_errors($password) as $message) {
    add_error($errors, 'password', $message);
}

if ($passwordConfirmation === '') {
    add_error($errors, 'password_confirmation', 'Confirme sua nova senha.');
} elseif (!hash_equals($password, $passwordConfirmation)) {
    add_error($errors, 'password_confirmation', 'A confirmacao deve ser igual a senha.');
}

if (has_errors($errors)) {
    $status = isset($errors['token']) ? 410 : 422;
    error_response('Verifique os campos informados.', $errors, $status);
}

try {
    update_user_password((int) $record['user_id'], $password);
    invalidate_user_reset_tokens((int) $record['user_id']);
} catch (\Throwable $throwable) {
    error_response('Nao foi possivel redefinir a senha agora.', [], 500);
}

log_audit('user.password_reset', [
    'actor_type' => 'user',
    'actor_id'   => $record['user_id'],
]);

success_response('Senha redefinida com sucesso.');


<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();

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
    error_response('E-mail ou senha invalidos.', [
        '_general' => ['E-mail ou senha invalidos.'],
    ], 401);
}

if ((bool) ($user['two_factor_enabled'] ?? false) && !empty($user['two_factor_secret_encrypted'])) {
    start_two_factor_pending((int) $user['id']);

    success_response('Codigo de verificacao necessario.', [
        'requires_2fa' => true,
        'csrf_token' => rotate_csrf_token(),
    ]);
}

$publicUser = login_user($user);

success_response('Login realizado com sucesso.', [
    'user' => $publicUser,
    'csrf_token' => rotate_csrf_token(),
]);

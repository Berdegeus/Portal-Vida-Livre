<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();

$data = request_data();
$name = sanitize_name((string) ($data['name'] ?? ''));
$email = normalize_email((string) ($data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');
$passwordConfirmation = (string) ($data['password_confirmation'] ?? '');
$lgpdConsent = filter_var($data['lgpd_consent'] ?? false, FILTER_VALIDATE_BOOLEAN);
$errors = [];

if ($name === '' || string_length($name) < 3) {
    add_error($errors, 'name', 'Informe seu nome com pelo menos 3 caracteres.');
} elseif (string_length($name) > 120) {
    add_error($errors, 'name', 'O nome deve ter no maximo 120 caracteres.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    add_error($errors, 'email', 'Informe um e-mail valido.');
}

foreach (password_strength_errors($password) as $message) {
    add_error($errors, 'password', $message);
}

if ($passwordConfirmation === '') {
    add_error($errors, 'password_confirmation', 'Confirme sua senha.');
} elseif (!hash_equals($password, $passwordConfirmation)) {
    add_error($errors, 'password_confirmation', 'A confirmacao deve ser igual a senha.');
}

if ($email !== '') {
    $existingUser = find_user_by_email($email);

    if ($existingUser !== null) {
        $message = user_email_is_verified($existingUser)
            ? 'Este e-mail ja esta cadastrado.'
            : 'Este e-mail ja possui um cadastro pendente. Tente entrar para receber um novo link de confirmacao.';

        add_error($errors, 'email', $message);
    }
}

if (!$lgpdConsent) {
    add_error($errors, 'lgpd_consent', 'Voce precisa aceitar os termos para continuar.');
}

if (has_errors($errors)) {
    error_response('Verifique os campos informados.', $errors, 422);
}

try {
    $userId = register_user_and_send_verification_email($name, $email, $password);
    $user = find_user_by_id($userId);
} catch (\Throwable $throwable) {
    error_response('Nao foi possivel concluir o cadastro agora. ' . $throwable->getMessage(), [], 500);
}

success_response('Cadastro realizado. Enviamos um link de confirmacao para seu e-mail.', [
    'user' => $user !== null ? user_public_data($user) : [
        'id' => $userId,
        'name' => $name,
        'email' => $email,
        'email_verified' => false,
        'created_at' => null,
    ],
], 201);


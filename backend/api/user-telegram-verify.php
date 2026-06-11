<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();

$pending = require_user_telegram_pending();
$userId  = (int) $pending['user_id'];

$data   = request_data();
$codigo = trim((string) ($data['codigo'] ?? ''));

if (!preg_match('/^\d{6}$/', $codigo)) {
    error_response('Informe o codigo de 6 digitos.', ['codigo' => ['Informe o codigo de 6 digitos.']], 422);
}

$record = find_user_telegram_login_code($userId, $codigo);

if ($record === null) {
    error_response('Codigo invalido ou expirado.', ['codigo' => ['Codigo invalido ou expirado.']], 401);
}

mark_user_telegram_codigo_usado((int) $record['id']);
clear_user_telegram_pending();

$user = find_user_by_id($userId);

if ($user === null) {
    error_response('Usuario nao encontrado.', [], 500);
}

$publicUser = login_user($user);

log_audit('user.login', [
    'actor_type'  => 'user',
    'actor_id'    => $user['id'],
    'actor_email' => $user['email'],
]);

success_response('Login realizado com sucesso.', [
    'user'       => $publicUser,
    'csrf_token' => rotate_csrf_token(),
]);

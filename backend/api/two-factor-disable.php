<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();

$sessionUser = current_user();

if ($sessionUser === null) {
    error_response('Sessao invalida.', [], 401);
}

$data = request_data();
$currentPassword = (string) ($data['current_password'] ?? '');
$errors = [];

if ($currentPassword === '') {
    add_error($errors, 'current_password', 'Informe sua senha atual.');
}

if (has_errors($errors)) {
    error_response('Verifique os campos informados.', $errors, 422);
}

$user = find_user_auth_by_id((int) $sessionUser['id']);

if ($user === null) {
    error_response('Sessao invalida.', [], 401);
}

if (!(bool) ($user['two_factor_enabled'] ?? false)) {
    error_response('O 2FA nao esta ativo para esta conta.', [], 400);
}

if (!verify_current_password((int) $user['id'], $currentPassword)) {
    error_response('Nao foi possivel desativar o 2FA.', [
        'current_password' => ['Senha atual invalida.'],
    ], 401);
}

try {
    disable_two_factor_for_user((int) $user['id']);
} catch (\Throwable $throwable) {
    error_response('Nao foi possivel desativar o 2FA.', [], 500);
}

success_response('2FA desativado com sucesso.', [
    'two_factor' => [
        'enabled' => false,
        'confirmed_at' => null,
        'setup_pending' => false,
        'backup_codes_remaining' => 0,
    ],
]);


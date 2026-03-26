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
    error_response('Ative o 2FA antes de regenerar backup codes.', [], 400);
}

if (!verify_current_password((int) $user['id'], $currentPassword)) {
    error_response('Nao foi possivel regenerar os backup codes.', [
        'current_password' => ['Senha atual invalida.'],
    ], 401);
}

try {
    $backupCodes = replace_backup_codes((int) $user['id']);
    $updatedUser = find_user_auth_by_id((int) $user['id']);
} catch (\Throwable $throwable) {
    error_response('Nao foi possivel regenerar os backup codes.', [], 500);
}

success_response('Backup codes regenerados com sucesso.', [
    'two_factor' => $updatedUser !== null ? two_factor_status_array($updatedUser) : null,
    'backup_codes' => $backupCodes,
]);


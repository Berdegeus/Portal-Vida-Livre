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
$code = (string) ($data['code'] ?? '');
$errors = [];

if ($currentPassword === '') {
    add_error($errors, 'current_password', 'Informe sua senha atual.');
}

if (!preg_match('/^\d{6}$/', $code)) {
    add_error($errors, 'code', 'Informe um codigo valido de 6 digitos.');
}

if (has_errors($errors)) {
    error_response('Verifique os campos informados.', $errors, 422);
}

$user = find_user_auth_by_id((int) $sessionUser['id']);

if ($user === null) {
    error_response('Sessao invalida.', [], 401);
}

if (!verify_current_password((int) $user['id'], $currentPassword)) {
    error_response('Nao foi possivel confirmar o 2FA.', [
        'current_password' => ['Senha atual invalida.'],
    ], 401);
}

if (empty($user['two_factor_temp_secret_encrypted'])) {
    error_response('Nao ha configuracao de 2FA pendente para confirmar.', [], 400);
}

$secret = get_pending_two_factor_secret($user);

if ($secret === null) {
    error_response('Nao ha configuracao de 2FA pendente para confirmar.', [], 400);
}

if (!verify_totp_code($secret, $code)) {
    error_response('Codigo de verificacao invalido.', [
        'code' => ['Codigo invalido. Verifique o app autenticador.'],
    ], 422);
}

try {
    enable_two_factor_for_user((int) $user['id'], (string) $user['two_factor_temp_secret_encrypted']);
    $backupCodes = replace_backup_codes((int) $user['id']);
    $updatedUser = find_user_auth_by_id((int) $user['id']);
} catch (\Throwable $throwable) {
    error_response('Nao foi possivel ativar o 2FA.', [], 500);
}

success_response('2FA ativado com sucesso.', [
    'two_factor' => $updatedUser !== null ? two_factor_status_array($updatedUser) : null,
    'backup_codes' => $backupCodes,
]);
<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();

$pending = get_two_factor_pending();

if ($pending === null) {
    error_response('Autenticacao 2FA nao iniciada ou expirada.', [], 401);
}

$data = request_data();
$code = trim((string) ($data['code'] ?? ''));
$backupCode = trim((string) ($data['backup_code'] ?? ''));
$errors = [];

if ($code === '' && $backupCode === '') {
    add_error($errors, 'code', 'Informe um codigo do authenticator ou um backup code.');
}

if ($code !== '' && !preg_match('/^\d{6}$/', $code)) {
    add_error($errors, 'code', 'Informe um codigo valido de 6 digitos.');
}

if (has_errors($errors)) {
    error_response('Verifique os campos informados.', $errors, 422);
}

$user = find_user_auth_by_id((int) $pending['user_id']);

if (
    $user === null ||
    !(bool) ($user['two_factor_enabled'] ?? false) ||
    empty($user['two_factor_secret_encrypted'])
) {
    clear_two_factor_pending();
    error_response('Autenticacao 2FA nao iniciada ou expirada.', [], 401);
}

$validated = false;

try {
    if ($backupCode !== '') {
        $validated = verify_and_consume_backup_code((int) $user['id'], $backupCode);
    } else {
        $secret = decrypt_sensitive_value((string) $user['two_factor_secret_encrypted']);
        $validated = verify_totp_code($secret, $code);
    }
} catch (\Throwable $throwable) {
    clear_two_factor_pending();
    error_response('Nao foi possivel validar o codigo 2FA.', [], 500);
}

if (!$validated) {
    $attempts = increment_two_factor_pending_attempts();
    $errorField = $backupCode !== '' ? 'backup_code' : 'code';

    if ($attempts >= TWO_FACTOR_PENDING_MAX_ATTEMPTS) {
        clear_two_factor_pending();
        error_response('Muitas tentativas invalidas. Faca login novamente.', [], 429);
    }

    error_response('Codigo de verificacao invalido.', [
        $errorField => ['Codigo invalido.'],
    ], 422);
}

$publicUser = login_user($user);

success_response('Verificacao 2FA concluida com sucesso.', [
    'user' => $publicUser,
    'csrf_token' => rotate_csrf_token(),
]);

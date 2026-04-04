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

if (!verify_current_password((int) $user['id'], $currentPassword)) {
    error_response('Nao foi possivel iniciar a configuracao do 2FA.', [
        'current_password' => ['Senha atual invalida.'],
    ], 401);
}

if ((bool) ($user['two_factor_enabled'] ?? false)) {
    error_response('O 2FA ja esta ativo para esta conta.', [], 409);
}

try {
    $secret     = totp_generate_secret();
    $otpauthUri = build_otpauth_uri((string) $user['email'], $secret);

    store_pending_two_factor_secret((int) $user['id'], $secret);

    $qrCodeUrl = build_qr_code_url($otpauthUri);

} catch (\Throwable $throwable) {
    error_log('[2FA Setup] ' . $throwable->getMessage() . ' em ' . $throwable->getFile() . ':' . $throwable->getLine());
    error_response('Nao foi possivel iniciar a configuracao do 2FA.', [], 500);
}

success_response('Configuracao inicial do 2FA gerada com sucesso.', [
    'secret'        => $secret,
    'otpauth_uri'   => $otpauthUri,
    'qr_code_url'   => $qrCodeUrl,
    'setup_pending' => true,
]);

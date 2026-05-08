<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();

$data = request_data();
$token = trim((string) ($data['token'] ?? ''));

if ($token === '') {
    error_response('O link informado e invalido ou expirou.', [
        'token' => ['O link informado e invalido ou expirou.'],
    ], 410);
}

$record = find_email_verification_token_record($token);

if ($record === null) {
    error_response('O link informado e invalido ou expirou.', [
        'token' => ['O link informado e invalido ou expirou.'],
    ], 410);
}

if (user_email_is_verified($record)) {
    success_response('Seu e-mail ja foi confirmado. Agora voce pode entrar.', [
        'verified' => true,
    ]);
}

$isExpired = strtotime((string) $record['expires_at']) < time();

if ($record['used_at'] !== null || $isExpired) {
    error_response('O link informado e invalido ou expirou.', [
        'token' => ['O link informado e invalido ou expirou.'],
    ], 410);
}

try {
    mark_user_email_as_verified((int) $record['user_id']);
    invalidate_user_email_verification_tokens((int) $record['user_id']);
} catch (\Throwable $throwable) {
    error_response('Nao foi possivel confirmar seu cadastro agora.', [], 500);
}

log_audit('user.email_verified', [
    'actor_type' => 'user',
    'actor_id'   => $record['user_id'],
]);

success_response('Cadastro confirmado com sucesso. Agora voce pode entrar.', [
    'verified' => true,
]);

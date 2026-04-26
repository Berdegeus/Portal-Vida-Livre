<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();

$data  = request_data();
$token = trim((string) ($data['token'] ?? ''));

if ($token === '') {
    error_response('Token invalido.', [], 422);
}

$record = find_admin_login_token($token);

if ($record === null) {
    error_response('Link invalido ou expirado. Solicite um novo acesso.', [
        '_general' => ['Link invalido ou expirado.'],
    ], 401);
}

$admin = find_admin_full_by_id((int) $record['admin_id']);

if ($admin === null) {
    error_response('Administrador nao encontrado.', [], 500);
}

// Cria o código ANTES de consumir o token.
// Se falhar aqui, o token permanece válido e o admin pode tentar de novo.
$temTelegram = $admin['telegram_chat_id'] !== null;
$tipo        = $temTelegram ? 'login' : 'vinculacao';
$codigo      = create_telegram_codigo((int) $admin['id'], $tipo);

// A partir daqui o token é consumido.
consume_admin_login_token((int) $record['id']);
start_admin_2fa_pending((int) $admin['id']);
cleanup_telegram_codigos();

if (!$temTelegram) {
    // Primeira vez: mostra tela de vinculação com o código
    success_response('Vincule seu Telegram.', [
        'requires_2fa' => true,
        'step'         => 'vinculacao',
        'codigo'       => $codigo,
        'bot_username' => telegram_bot_username(),
        'csrf_token'   => rotate_csrf_token(),
    ]);
}

// Logins seguintes: envia código de 6 dígitos pelo Telegram
$chatId = (int) $admin['telegram_chat_id'];

try {
    telegram_send_message(
        $chatId,
        "Código de acesso ao Portal Vida Livre: {$codigo}\n\nVálido por 5 minutos. Não compartilhe."
    );
} catch (\Throwable $e) {
    error_response('Nao foi possivel enviar o codigo pelo Telegram. Tente novamente.', [], 503);
}

success_response('Codigo enviado pelo Telegram.', [
    'requires_2fa' => true,
    'step'         => 'login',
    'csrf_token'   => rotate_csrf_token(),
]);

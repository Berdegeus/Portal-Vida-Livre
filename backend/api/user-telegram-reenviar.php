<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();

$pending = require_user_telegram_pending();
$userId  = (int) $pending['user_id'];

// Rate limit: 30 segundos entre reenvios.
$lastReenviar = (int) ($_SESSION['user_tg_last_reenviar'] ?? 0);
$elapsed      = time() - $lastReenviar;

if ($lastReenviar > 0 && $elapsed < 30) {
    $aguarde = 30 - $elapsed;
    error_response("Aguarde {$aguarde} segundo(s) antes de reenviar.", [], 429);
}

$_SESSION['user_tg_last_reenviar'] = time();

$chatId = find_user_telegram_chat_id($userId);

if ($chatId === null) {
    error_response('Telegram nao vinculado. Reinicie o login.', [], 409);
}

$codigo = create_user_telegram_codigo($userId, 'login');

try {
    telegram_send_message(
        $chatId,
        "Novo codigo de acesso — Portal Vida Livre:\n\n{$codigo}\n\nExpira em 10 minutos."
    );
} catch (\Throwable $e) {
    fwrite(STDERR, '[user-telegram-reenviar] Erro: ' . $e->getMessage() . "\n");
    error_response('Nao foi possivel reenviar o codigo. Tente novamente.', [], 503);
}

success_response('Novo codigo enviado ao seu Telegram.', [
    'csrf_token' => rotate_csrf_token(),
]);

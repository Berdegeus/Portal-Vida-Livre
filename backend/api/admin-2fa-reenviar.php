<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();

$pending = require_admin_2fa_pending();
$adminId = (int) $pending['admin_id'];

// Rate limit via sessão: 30 segundos entre reenvios
$lastReenviar = (int) ($_SESSION['admin_2fa_last_reenviar'] ?? 0);
$elapsed      = time() - $lastReenviar;

if ($lastReenviar > 0 && $elapsed < 30) {
    $aguarde = 30 - $elapsed;
    error_response("Aguarde {$aguarde} segundo(s).", [], 429);
}

$_SESSION['admin_2fa_last_reenviar'] = time();

$admin = find_admin_full_by_id($adminId);

if ($admin === null) {
    error_response('Administrador nao encontrado.', [], 500);
}

$temTelegram = $admin['telegram_chat_id'] !== null;
$tipo        = $temTelegram ? 'login' : 'vinculacao';
$codigo      = create_telegram_codigo($adminId, $tipo);

if ($temTelegram) {
    try {
        telegram_send_message(
            (int) $admin['telegram_chat_id'],
            "Novo código de acesso: {$codigo}\n\nVálido por 5 minutos."
        );
    } catch (\Throwable $e) {
        error_response('Nao foi possivel enviar o codigo pelo Telegram.', [], 503);
    }

    success_response('Novo codigo enviado pelo Telegram.', [
        'csrf_token' => rotate_csrf_token(),
    ]);
}

// Vinculação: retorna o código para exibir na tela
success_response('Novo codigo gerado.', [
    'codigo'     => $codigo,
    'csrf_token' => rotate_csrf_token(),
]);

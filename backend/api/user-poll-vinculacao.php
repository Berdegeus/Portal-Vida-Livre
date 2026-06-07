<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();

$pending = require_user_telegram_pending();
$userId  = (int) $pending['user_id'];

$chatId = find_user_telegram_chat_id($userId);

if ($chatId !== null) {
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

    success_response('Telegram vinculado. Login realizado.', [
        'vinculado'  => true,
        'user'       => $publicUser,
        'csrf_token' => rotate_csrf_token(),
    ]);
}

// Verificar se o código de vinculação ainda está ativo
$stmt = db()->prepare(
    'SELECT id FROM user_telegram_codigos
     WHERE user_id   = :user_id
       AND tipo      = \'vinculacao\'
       AND usado     = 0
       AND criado_em > NOW() - INTERVAL 10 MINUTE
     LIMIT 1'
);
$stmt->execute(['user_id' => $userId]);
$codigoAtivo = $stmt->fetch();

success_response('Aguardando vinculacao.', [
    'vinculado'  => false,
    'expirado'   => $codigoAtivo === false,
    'csrf_token' => rotate_csrf_token(),
]);

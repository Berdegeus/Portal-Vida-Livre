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
    // Telegram foi vinculado — enviar OTP de login antes de autenticar.
    // Isso garante que apenas quem controla o Telegram vinculado consegue completar o login.
    $codigo = create_user_telegram_codigo($userId, 'login');
    $sent   = notify_user_telegram_login($chatId, $codigo);

    if (!$sent) {
        error_response('Nao foi possivel enviar o codigo de verificacao. Tente novamente.', [], 503);
    }

    success_response('Telegram vinculado. Insira o codigo enviado ao seu Telegram para continuar.', [
        'vinculado'  => true,
        'step'       => 'verify_otp',
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

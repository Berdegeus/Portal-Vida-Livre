<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();

$pending = require_admin_2fa_pending();
$adminId = (int) $pending['admin_id'];

$admin = find_admin_full_by_id($adminId);

if ($admin === null) {
    error_response('Administrador nao encontrado.', [], 500);
}

// O bot (script separado) é quem processa o código e salva o chat_id.
// Aqui apenas verificamos se a vinculação já aconteceu.
if ($admin['telegram_chat_id'] !== null) {
    clear_admin_2fa_pending();
    $publicAdmin = login_admin($admin);

    success_response('Acesso autorizado.', [
        'vinculado'  => true,
        'admin'      => $publicAdmin,
        'csrf_token' => rotate_csrf_token(),
    ]);
}

// Verifica se o código de vinculação ainda está ativo
$stmt = db()->prepare(
    'SELECT id FROM telegram_codigos
     WHERE admin_id  = :admin_id
       AND tipo      = \'vinculacao\'
       AND usado     = 0
       AND criado_em > NOW() - INTERVAL 5 MINUTE
     LIMIT 1'
);
$stmt->execute(['admin_id' => $adminId]);
$codigoAtivo = $stmt->fetch();

success_response('Aguardando vinculacao.', [
    'vinculado' => false,
    'expirado'  => $codigoAtivo === false,
    'csrf_token' => rotate_csrf_token(),
]);

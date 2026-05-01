<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();

$_sessionUser = current_user();
log_audit('user.logout', [
    'actor_type'  => $_sessionUser !== null ? 'user' : 'system',
    'actor_id'    => $_sessionUser['id']    ?? null,
    'actor_email' => $_sessionUser['email'] ?? null,
]);

logout_user();

success_response('Sessao encerrada com sucesso.', [
    'csrf_token' => get_csrf_token(),
]);


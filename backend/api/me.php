<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'GET') {
    error_response('Metodo nao permitido.', [], 405);
}

$user = current_user();

success_response('Sessao consultada com sucesso.', [
    'authenticated' => $user !== null,
    'user' => $user,
    'login_two_factor_pending' => get_two_factor_pending() !== null,
    'csrf_token' => get_csrf_token(),
]);

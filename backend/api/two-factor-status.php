<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'GET') {
    error_response('Metodo nao permitido.', [], 405);
}

$user = current_user();

if ($user !== null) {
    $authUser = find_user_auth_by_id((int) $user['id']);

    success_response('Status do 2FA consultado com sucesso.', [
        'authenticated' => true,
        'login_pending' => false,
        'two_factor' => $authUser !== null ? two_factor_status_array($authUser) : null,
    ]);
}

success_response('Status do 2FA consultado com sucesso.', [
    'authenticated' => false,
    'login_pending' => get_two_factor_pending() !== null,
    'two_factor' => null,
]);


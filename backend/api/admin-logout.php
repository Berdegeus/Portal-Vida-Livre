<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();
$_adminLogout = require_admin();

log_audit('admin.logout', [
    'actor_type'  => 'admin',
    'actor_id'    => $_adminLogout['id'],
    'actor_email' => $_adminLogout['email'],
]);

logout_admin();

success_response('Sessao administrativa encerrada.', [
    'csrf_token' => rotate_csrf_token(),
]);

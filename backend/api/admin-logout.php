<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();
require_admin();

logout_admin();

success_response('Sessao administrativa encerrada.', [
    'csrf_token' => rotate_csrf_token(),
]);

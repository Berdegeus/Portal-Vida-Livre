<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();

$data  = request_data();
$token = trim((string) ($data['token'] ?? ''));

if ($token === '') {
    error_response('Token invalido ou expirado.', ['_general' => ['Token invalido ou expirado.']], 422);
}

$record = find_admin_login_token($token);

if ($record === null) {
    error_response('Link invalido ou expirado. Solicite um novo acesso.', [
        '_general' => ['Link invalido ou expirado. Solicite um novo acesso.'],
    ], 401);
}

consume_admin_login_token((int) $record['id']);

$admin = find_admin_by_id((int) $record['admin_id']);

if ($admin === null) {
    error_response('Administrador nao encontrado.', [], 500);
}

$publicAdmin = login_admin($admin);

success_response('Acesso autorizado.', [
    'admin'      => $publicAdmin,
    'csrf_token' => rotate_csrf_token(),
]);

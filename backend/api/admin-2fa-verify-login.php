<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();

$pending = require_admin_2fa_pending();
$adminId = (int) $pending['admin_id'];

$data   = request_data();
$codigo = trim((string) ($data['codigo'] ?? ''));

if (!preg_match('/^\d{6}$/', $codigo)) {
    error_response('Informe o codigo de 6 digitos.', ['codigo' => ['Informe o codigo de 6 digitos.']], 422);
}

$record = find_telegram_codigo_login($adminId, $codigo);

if ($record === null) {
    error_response('Codigo invalido ou expirado.', ['codigo' => ['Codigo invalido ou expirado.']], 401);
}

marcar_codigo_usado((int) $record['id']);
clear_admin_2fa_pending();

$admin = find_admin_by_id($adminId);

if ($admin === null) {
    error_response('Administrador nao encontrado.', [], 500);
}

$publicAdmin = login_admin($admin);

success_response('Acesso autorizado.', [
    'admin'      => $publicAdmin,
    'csrf_token' => rotate_csrf_token(),
]);

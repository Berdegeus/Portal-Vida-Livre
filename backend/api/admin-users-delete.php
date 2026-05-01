<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();
require_admin();

$data = request_data();
$userId = (int) ($data['id'] ?? 0);

if ($userId <= 0) {
    error_response('ID invalido.', [], 422);
}

$stmt = db()->prepare('DELETE FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);

if ($stmt->rowCount() === 0) {
    error_response('Usuario nao encontrado.', [], 404);
}

success_response('Usuario excluido com sucesso.', ['csrf_token' => rotate_csrf_token()]);

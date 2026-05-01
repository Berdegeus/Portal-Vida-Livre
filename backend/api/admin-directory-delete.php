<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();
require_admin();

$data = request_data();
$entryId = (int) ($data['id'] ?? 0);

if ($entryId <= 0) {
    error_response('ID invalido.', [], 422);
}

$stmt = db()->prepare('DELETE FROM directory_entries WHERE id = :id');
$stmt->execute(['id' => $entryId]);

if ($stmt->rowCount() === 0) {
    error_response('Entrada nao encontrada.', [], 404);
}

success_response('Entrada excluida com sucesso.', ['csrf_token' => rotate_csrf_token()]);

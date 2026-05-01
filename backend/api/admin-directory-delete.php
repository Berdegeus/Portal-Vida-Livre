<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();
$admin = require_admin();

$data    = request_data();
$entryId = (int) ($data['id'] ?? 0);

if ($entryId <= 0) {
    error_response('ID invalido.', [], 422);
}

$lookupStmt = db()->prepare('SELECT name FROM directory_entries WHERE id = :id');
$lookupStmt->execute(['id' => $entryId]);
$entryToDelete = $lookupStmt->fetch(\PDO::FETCH_ASSOC);

if (!$entryToDelete) {
    error_response('Entrada nao encontrada.', [], 404);
}

$stmt = db()->prepare('DELETE FROM directory_entries WHERE id = :id');
$stmt->execute(['id' => $entryId]);

log_audit('admin.directory_deleted', [
    'actor_type'   => 'admin',
    'actor_id'     => $admin['id'],
    'actor_email'  => $admin['email'],
    'target_type'  => 'directory_entry',
    'target_id'    => $entryId,
    'target_label' => $entryToDelete['name'],
]);

success_response('Entrada excluida com sucesso.', ['csrf_token' => rotate_csrf_token()]);

<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();
$admin = require_admin();

$data   = request_data();
$userId = (int) ($data['id'] ?? 0);

if ($userId <= 0) {
    error_response('ID invalido.', [], 422);
}

$lookupStmt = db()->prepare('SELECT email FROM users WHERE id = :id');
$lookupStmt->execute(['id' => $userId]);
$userToDelete = $lookupStmt->fetch(\PDO::FETCH_ASSOC);

if (!$userToDelete) {
    error_response('Usuario nao encontrado.', [], 404);
}

$stmt = db()->prepare('DELETE FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);

log_audit('admin.user_deleted', [
    'actor_type'   => 'admin',
    'actor_id'     => $admin['id'],
    'actor_email'  => $admin['email'],
    'target_type'  => 'user',
    'target_id'    => $userId,
    'target_label' => $userToDelete['email'],
]);

success_response('Usuario excluido com sucesso.', ['csrf_token' => rotate_csrf_token()]);

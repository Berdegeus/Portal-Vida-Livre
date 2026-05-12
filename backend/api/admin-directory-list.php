<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'GET') {
    error_response('Metodo nao permitido.', [], 405);
}

$admin = require_admin();

log_audit('admin.directory_viewed', [
    'actor_type'  => 'admin',
    'actor_id'    => $admin['id'],
    'actor_email' => $admin['email'],
]);

$stmt = admin_db()->query(
    'SELECT id, slug, entry_type, name, specialty, city, state, service_mode, is_active, created_at
     FROM vw_admin_directory_entries
     ORDER BY created_at DESC'
);
$entries = $stmt->fetchAll(\PDO::FETCH_ASSOC);

success_response('OK', ['entries' => $entries]);

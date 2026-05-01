<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'GET') {
    error_response('Metodo nao permitido.', [], 405);
}

require_admin();

$stmt = db()->query(
    'SELECT id, slug, entry_type, name, specialty, city, state, service_mode, is_active, created_at
     FROM directory_entries
     ORDER BY created_at DESC'
);
$entries = $stmt->fetchAll(\PDO::FETCH_ASSOC);

success_response('OK', ['entries' => $entries]);

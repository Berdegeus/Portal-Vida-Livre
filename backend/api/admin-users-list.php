<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'GET') {
    error_response('Metodo nao permitido.', [], 405);
}

require_admin();

$stmt = db()->query(
    'SELECT id, name, email, email_verified_at, lgpd_consent_at, two_factor_enabled, created_at
     FROM users
     ORDER BY created_at DESC'
);
$users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

success_response('OK', ['users' => $users]);

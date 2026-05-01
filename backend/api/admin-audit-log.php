<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'GET') {
    error_response('Metodo nao permitido.', [], 405);
}

require_admin();

$page    = max(1, (int) ($_GET['page']     ?? 1));
$perPage = min(200, max(10, (int) ($_GET['per_page'] ?? 50)));
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

$actionFilter    = trim((string) ($_GET['action']     ?? ''));
$actorTypeFilter = trim((string) ($_GET['actor_type'] ?? ''));

if ($actionFilter !== '') {
    $where[]          = 'action = :action';
    $params['action'] = $actionFilter;
}

if (in_array($actorTypeFilter, ['user', 'admin', 'system'], true)) {
    $where[]               = 'actor_type = :actor_type';
    $params['actor_type']  = $actorTypeFilter;
}

$whereClause = implode(' AND ', $where);

$countStmt = db()->prepare("SELECT COUNT(*) FROM audit_logs WHERE {$whereClause}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$stmt = db()->prepare(
    "SELECT id, actor_type, actor_id, actor_email, action,
            target_type, target_id, target_label, ip, created_at
     FROM audit_logs
     WHERE {$whereClause}
     ORDER BY created_at DESC
     LIMIT {$perPage} OFFSET {$offset}"
);
$stmt->execute($params);
$logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

success_response('OK', [
    'logs'     => $logs,
    'total'    => $total,
    'page'     => $page,
    'per_page' => $perPage,
]);

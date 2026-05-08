<?php

declare(strict_types=1);

function log_audit(string $action, array $context = []): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO audit_logs
                (actor_type, actor_id, actor_email, action, target_type, target_id, target_label, ip)
             VALUES
                (:actor_type, :actor_id, :actor_email, :action, :target_type, :target_id, :target_label, :ip)'
        );

        $stmt->execute([
            'actor_type'   => $context['actor_type']  ?? 'system',
            'actor_id'     => isset($context['actor_id'])  ? (int) $context['actor_id']  : null,
            'actor_email'  => $context['actor_email']  ?? null,
            'action'       => $action,
            'target_type'  => $context['target_type']  ?? null,
            'target_id'    => isset($context['target_id']) ? (int) $context['target_id'] : null,
            'target_label' => $context['target_label'] ?? null,
            'ip'           => audit_client_ip(),
        ]);
    } catch (\Throwable $e) {
        error_log('[Audit] ' . $e->getMessage());
    }
}

function audit_client_ip(): ?string
{
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return null;
}

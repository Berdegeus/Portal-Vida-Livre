CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_directory_public AS
SELECT
    id,
    slug,
    entry_type,
    name,
    specialty,
    city,
    state,
    service_mode,
    short_bio,
    created_at,
    updated_at
FROM directory_entries
WHERE is_active = 1;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_directory_home_stats AS
SELECT
    COALESCE(SUM(CASE WHEN entry_type = 'professional' AND is_active = 1 THEN 1 ELSE 0 END), 0) AS specialists_total,
    COALESCE(SUM(CASE WHEN entry_type = 'support_group' AND is_active = 1 THEN 1 ELSE 0 END), 0) AS support_groups_total,
    (SELECT COUNT(*) FROM users) AS transformed_lives_total,
    COUNT(DISTINCT CASE WHEN is_active = 1 THEN CONCAT(city, '|', state) END) AS cities_total
FROM directory_entries;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_directory_specialties AS
SELECT
    specialty,
    COUNT(*) AS entries_total
FROM directory_entries
WHERE is_active = 1
GROUP BY specialty;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_directory_locations AS
SELECT
    city,
    state,
    CONCAT(city, ', ', state) AS location,
    COUNT(*) AS entries_total
FROM directory_entries
WHERE is_active = 1
GROUP BY city, state;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_admin_users AS
SELECT
    id,
    name,
    email,
    email_verified_at,
    lgpd_consent_at,
    two_factor_enabled,
    two_factor_confirmed_at,
    created_at,
    updated_at
FROM users;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_admin_directory_entries AS
SELECT
    id,
    slug,
    entry_type,
    name,
    specialty,
    city,
    state,
    service_mode,
    short_bio,
    is_active,
    created_at,
    updated_at
FROM directory_entries;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_admin_audit_logs AS
SELECT
    id,
    actor_type,
    actor_id,
    actor_email,
    action,
    target_type,
    target_id,
    target_label,
    ip,
    created_at
FROM audit_logs;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_report_directory_summary AS
SELECT
    entry_type,
    specialty,
    city,
    state,
    COUNT(*) AS entries_total,
    COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) AS active_entries_total,
    COALESCE(SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END), 0) AS inactive_entries_total,
    MIN(created_at) AS first_entry_at,
    MAX(created_at) AS latest_entry_at
FROM directory_entries
GROUP BY entry_type, specialty, city, state;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_report_security_events AS
SELECT
    id,
    actor_type,
    actor_id,
    actor_email,
    action,
    target_type,
    target_id,
    target_label,
    ip,
    created_at,
    CASE
        WHEN action LIKE '%failed%' OR action LIKE '%locked%' THEN 'high'
        WHEN action LIKE '%deleted%' OR action LIKE '%password%' OR action LIKE '%2fa%' THEN 'medium'
        ELSE 'low'
    END AS severity
FROM audit_logs
WHERE action IN (
        'user.login',
        'user.login_failed',
        'user.login_2fa_required',
        'user.login_2fa_completed',
        'user.login_2fa_failed',
        'user.logout',
        'user.password_reset_requested',
        'user.password_reset',
        'user.password_changed',
        'user.2fa_enabled',
        'user.2fa_disabled',
        'user.account_deleted',
        'admin.login',
        'admin.login_via_security_questions',
        'admin.security_questions_locked',
        'admin.logout',
        'admin.user_deleted',
        'admin.directory_deleted'
    );

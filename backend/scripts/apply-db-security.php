<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once dirname(__DIR__) . '/core/env.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/secrets.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/schema.php';

const SECURITY_DB_HOST = '127.0.0.1';

load_env(dirname(__DIR__) . '/.env');

function security_env_specs(): array
{
    return [
        'DB_USERNAME' => 'portal_app_runtime',
        'DB_PUBLIC_USERNAME' => 'portal_public_reader',
        'DB_ADMIN_USERNAME' => 'portal_admin_runtime',
        'DB_REPORT_USERNAME' => 'portal_report_reader',
    ];
}

function security_password_env_for_user_env(string $userEnv): string
{
    return match ($userEnv) {
        'DB_USERNAME' => 'DB_PASSWORD',
        'DB_PUBLIC_USERNAME' => 'DB_PUBLIC_PASSWORD',
        'DB_ADMIN_USERNAME' => 'DB_ADMIN_PASSWORD',
        'DB_REPORT_USERNAME' => 'DB_REPORT_PASSWORD',
        default => throw new \InvalidArgumentException('Usuario desconhecido: ' . $userEnv),
    };
}

function security_set_env(string $key, string $value): void
{
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
    putenv($key . '=' . $value);
}

function security_password_needs_rotation(?string $value): bool
{
    $value = trim((string) $value);

    if ($value === '') {
        return true;
    }

    $lower = strtolower($value);

    return str_contains($lower, 'senha-gerada')
        || str_contains($lower, 'senha-root')
        || str_contains($lower, 'placeholder')
        || str_contains($lower, 'troque');
}

function security_generate_password(): string
{
    return rtrim(strtr(base64_encode(random_bytes(30)), '+/', '-_'), '=') . 'aA1!';
}

function security_write_env(string $path, array $updates): void
{
    if ($updates === []) {
        return;
    }

    $lines = file_exists($path)
        ? (file($path, FILE_IGNORE_NEW_LINES) ?: [])
        : [];

    $seen = [];

    foreach ($lines as $index => $line) {
        if (!preg_match('/^\s*([A-Z0-9_]+)\s*=/', $line, $matches)) {
            continue;
        }

        $key = $matches[1];

        if (!array_key_exists($key, $updates)) {
            continue;
        }

        if ($updates[$key] === null) {
            unset($lines[$index]);
        } else {
            $lines[$index] = $key . '=' . $updates[$key];
        }

        $seen[$key] = true;
    }

    foreach ($updates as $key => $value) {
        if ($value !== null && !isset($seen[$key])) {
            $lines[] = $key . '=' . $value;
        }
    }

    file_put_contents($path, implode(PHP_EOL, array_values($lines)) . PHP_EOL);
}

function security_prepare_env_file(): array
{
    $envPath = dirname(__DIR__) . '/.env';
    $updates = [];

    $originalDbUsername = (string) env('DB_USERNAME', 'root');
    $originalDbPassword = (string) secret_value('DB_PASSWORD', '');

    if (env('DB_MAINTENANCE_USERNAME') === null) {
        $updates['DB_MAINTENANCE_USERNAME'] = $originalDbUsername;
    }

    if (secret_value('DB_MAINTENANCE_PASSWORD') === null) {
        $updates['DB_MAINTENANCE_PASSWORD_OBFUSCATED'] = obfuscate_secret($originalDbPassword);
    }

    foreach (security_env_specs() as $userEnv => $expectedUsername) {
        $passwordEnv = security_password_env_for_user_env($userEnv);
        $currentUsername = (string) env($userEnv, '');
        $currentPassword = secret_value($passwordEnv);

        if ($currentUsername !== $expectedUsername) {
            $updates[$userEnv] = $expectedUsername;
        }

        if ($currentUsername !== $expectedUsername || security_password_needs_rotation($currentPassword)) {
            $updates[$passwordEnv] = null;
            $updates[$passwordEnv . '_OBFUSCATED'] = obfuscate_secret(security_generate_password());
        }
    }

    foreach ($updates as $key => $value) {
        security_set_env($key, (string) $value);
    }

    security_write_env($envPath, $updates);

    return $updates;
}

function security_views_file_path(): string
{
    return dirname(__DIR__) . '/database/views.sql';
}

function security_apply_views(): void
{
    $sql = file_get_contents(security_views_file_path());

    if ($sql === false) {
        throw new \RuntimeException('Nao foi possivel ler o views.sql.');
    }

    foreach (split_sql_statements($sql) as $statement) {
        maintenance_db()->exec($statement);
    }
}

function security_quote_identifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function security_account(\PDO $pdo, string $username): string
{
    return $pdo->quote($username) . '@' . $pdo->quote(SECURITY_DB_HOST);
}

function security_database_name(): string
{
    $config = load_config('database');

    return (string) $config['database'];
}

function security_create_or_update_user(\PDO $pdo, string $username, string $password): void
{
    $account = security_account($pdo, $username);
    $quotedPassword = $pdo->quote($password);

    $pdo->exec('CREATE USER IF NOT EXISTS ' . $account . ' IDENTIFIED BY ' . $quotedPassword);
    $pdo->exec('ALTER USER ' . $account . ' IDENTIFIED BY ' . $quotedPassword);
    $pdo->exec('REVOKE ALL PRIVILEGES, GRANT OPTION FROM ' . $account);
}

function security_grant(\PDO $pdo, string $username, string $privileges, string $object): void
{
    $database = security_quote_identifier(security_database_name());
    $quotedObject = security_quote_identifier($object);

    $pdo->exec(sprintf(
        'GRANT %s ON %s.%s TO %s',
        $privileges,
        $database,
        $quotedObject,
        security_account($pdo, $username)
    ));
}

function security_apply_runtime_grants(\PDO $pdo, string $username): void
{
    $grants = [
        'users' => 'SELECT, INSERT, UPDATE, DELETE',
        'email_verification_tokens' => 'SELECT, INSERT, UPDATE, DELETE',
        'password_reset_tokens' => 'SELECT, INSERT, UPDATE, DELETE',
        'user_backup_codes' => 'SELECT, INSERT, UPDATE, DELETE',
        'user_directory_subscriptions' => 'SELECT, INSERT, DELETE',
        'directory_entries' => 'SELECT, INSERT',
        'audit_logs' => 'INSERT',
        'admins' => 'SELECT, UPDATE (telegram_chat_id)',
        'admin_login_tokens' => 'SELECT, INSERT, UPDATE, DELETE',
        'telegram_codigos' => 'SELECT, INSERT, UPDATE, DELETE',
        'admin_security_questions' => 'SELECT',
        'admin_security_question_lockouts' => 'SELECT, INSERT, UPDATE, DELETE',
        'user_telegram_codigos' => 'SELECT, INSERT, UPDATE, DELETE',
    ];

    foreach ($grants as $table => $privileges) {
        security_grant($pdo, $username, $privileges, $table);
    }
}

function security_apply_public_grants(\PDO $pdo, string $username): void
{
    foreach ([
        'vw_directory_public',
        'vw_directory_home_stats',
        'vw_directory_specialties',
        'vw_directory_locations',
    ] as $view) {
        security_grant($pdo, $username, 'SELECT', $view);
    }
}

function security_apply_admin_grants(\PDO $pdo, string $username): void
{
    foreach ([
        'vw_admin_users',
        'vw_admin_directory_entries',
        'vw_admin_audit_logs',
    ] as $view) {
        security_grant($pdo, $username, 'SELECT', $view);
    }

    security_grant($pdo, $username, 'SELECT (id), DELETE', 'users');
    security_grant($pdo, $username, 'SELECT (id), DELETE', 'directory_entries');
}

function security_apply_report_grants(\PDO $pdo, string $username): void
{
    foreach ([
        'vw_report_directory_summary',
        'vw_report_security_events',
    ] as $view) {
        security_grant($pdo, $username, 'SELECT', $view);
    }
}

function security_current_users(): array
{
    return [
        'runtime' => (string) env('DB_USERNAME'),
        'public' => (string) env('DB_PUBLIC_USERNAME'),
        'admin' => (string) env('DB_ADMIN_USERNAME'),
        'report' => (string) env('DB_REPORT_USERNAME'),
    ];
}

function security_current_passwords(): array
{
    return [
        'runtime' => required_secret('DB_PASSWORD'),
        'public' => required_secret('DB_PUBLIC_PASSWORD'),
        'admin' => required_secret('DB_ADMIN_PASSWORD'),
        'report' => required_secret('DB_REPORT_PASSWORD'),
    ];
}

function security_apply_users_and_grants(): void
{
    $pdo = maintenance_db(false);
    $users = security_current_users();
    $passwords = security_current_passwords();

    foreach ($users as $role => $username) {
        security_create_or_update_user($pdo, $username, $passwords[$role]);
    }

    security_apply_runtime_grants($pdo, $users['runtime']);
    security_apply_public_grants($pdo, $users['public']);
    security_apply_admin_grants($pdo, $users['admin']);
    security_apply_report_grants($pdo, $users['report']);

    $pdo->exec('FLUSH PRIVILEGES');
}

function security_fetch_grants(\PDO $pdo, string $username): array
{
    $statement = $pdo->query('SHOW GRANTS FOR ' . security_account($pdo, $username));
    $rows = $statement->fetchAll(\PDO::FETCH_NUM) ?: [];

    return array_map(static fn (array $row): string => (string) $row[0], $rows);
}

function security_assert_runtime_has_no_ddl(array $grants): void
{
    $joined = strtoupper(implode("\n", $grants));

    foreach (['CREATE', 'DROP', 'ALTER'] as $privilege) {
        if (preg_match('/\b' . $privilege . '\b/', $joined)) {
            throw new \RuntimeException('Grant indevido encontrado para runtime: ' . $privilege);
        }
    }

    if (str_contains($joined, 'GRANT OPTION')) {
        throw new \RuntimeException('Grant option indevido encontrado para runtime.');
    }
}

function security_open_named_connection(string $role): \PDO
{
    return match ($role) {
        'runtime' => db(),
        'public' => public_db(),
        'admin' => admin_db(),
        'report' => report_db(),
        default => throw new \InvalidArgumentException('Conexao desconhecida: ' . $role),
    };
}

function security_assert_query_allowed(\PDO $pdo, string $sql, string $label): void
{
    $pdo->query($sql);
    echo "[OK] Permitido: {$label}\n";
}

function security_assert_query_denied(\PDO $pdo, string $sql, string $label): void
{
    try {
        $pdo->query($sql);
    } catch (\PDOException) {
        echo "[OK] Negado: {$label}\n";
        return;
    }

    throw new \RuntimeException('A consulta deveria ter sido negada: ' . $label);
}

function security_validate_views(): void
{
    $rows = maintenance_db()->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")
        ->fetchAll(\PDO::FETCH_NUM) ?: [];
    $views = array_map(static fn (array $row): string => (string) $row[0], $rows);
    sort($views);

    echo "Views encontradas:\n";
    foreach ($views as $view) {
        echo " - {$view}\n";
    }
}

function security_validate_access(): void
{
    $database = security_quote_identifier(security_database_name());
    $public = security_open_named_connection('public');
    $runtime = security_open_named_connection('runtime');
    $admin = security_open_named_connection('admin');
    $report = security_open_named_connection('report');

    security_assert_query_allowed($public, 'SELECT COUNT(*) FROM vw_directory_public', 'public_reader consulta vw_directory_public');
    security_assert_query_denied($public, 'SELECT COUNT(*) FROM users', 'public_reader consulta users');
    security_assert_query_denied($public, 'SELECT COUNT(*) FROM admins', 'public_reader consulta admins');
    security_assert_query_denied($public, 'SELECT COUNT(*) FROM directory_entries', 'public_reader consulta tabela real directory_entries');
    security_assert_query_allowed($admin, 'SELECT COUNT(*) FROM vw_admin_users', 'admin_runtime consulta vw_admin_users');
    security_assert_query_denied($admin, 'SELECT email FROM users LIMIT 1', 'admin_runtime consulta coluna sensivel em users');
    security_assert_query_allowed($report, 'SELECT COUNT(*) FROM vw_report_security_events', 'report_reader consulta vw_report_security_events');
    security_assert_query_denied($report, 'SELECT COUNT(*) FROM audit_logs', 'report_reader consulta audit_logs');

    $runtimeCreatedProbe = false;

    try {
        $runtime->exec('CREATE TABLE ' . $database . '.__portal_privilege_probe (id INT)');
        $runtimeCreatedProbe = true;
    } catch (\PDOException) {
        echo "[OK] Negado: runtime cria tabela\n";
    }

    maintenance_db()->exec('DROP TABLE IF EXISTS ' . $database . '.__portal_privilege_probe');

    if ($runtimeCreatedProbe) {
        throw new \RuntimeException('Runtime conseguiu criar tabela; privilegio CREATE indevido.');
    }
}

function security_validate_grants(): void
{
    $pdo = maintenance_db(false);

    foreach (security_current_users() as $role => $username) {
        echo "\nSHOW GRANTS para {$username}@" . SECURITY_DB_HOST . " ({$role}):\n";
        $grants = security_fetch_grants($pdo, $username);

        foreach ($grants as $grant) {
            echo $grant . "\n";
        }

        if ($role === 'runtime') {
            security_assert_runtime_has_no_ddl($grants);
            echo "[OK] Runtime sem CREATE, DROP, ALTER ou GRANT OPTION.\n";
        }
    }
}

try {
    $envUpdates = security_prepare_env_file();

    if ($envUpdates !== []) {
        echo "backend/.env atualizado com credenciais locais para usuarios de menor privilegio.\n";
    }

    ensure_database_exists();
    security_apply_views();
    echo "Views aplicadas com sucesso.\n";

    security_apply_users_and_grants();
    echo "Usuarios e grants aplicados com sucesso.\n";

    security_validate_views();
    security_validate_grants();
    security_validate_access();

    echo "\nSeguranca do banco aplicada e validada com sucesso.\n";
} catch (\Throwable $throwable) {
    fwrite(STDERR, '[ERRO] ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}

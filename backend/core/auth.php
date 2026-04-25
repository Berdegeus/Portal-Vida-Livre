<?php

declare(strict_types=1);

const TWO_FACTOR_PENDING_MAX_ATTEMPTS = 5;
const TWO_FACTOR_PENDING_TTL = 300;

function ensure_session_started(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = is_secure_request();

    session_name('portal_vida_livre_session');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function user_public_data(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'name' => (string) $user['name'],
        'email' => (string) $user['email'],
        'email_verified' => user_email_is_verified($user),
        'created_at' => $user['created_at'] ?? null,
        'two_factor_enabled' => (bool) ($user['two_factor_enabled'] ?? false),
        'two_factor_confirmed_at' => $user['two_factor_confirmed_at'] ?? null,
    ];
}

function user_email_is_verified(array $user): bool
{
    return !empty($user['email_verified_at']);
}

function find_user_by_email(string $email): ?array
{
    $statement = db()->prepare(
       'SELECT id, name, email, password_hash, created_at, email_verified_at,
                two_factor_enabled, two_factor_secret_encrypted,
                two_factor_temp_secret_encrypted,
                two_factor_confirmed_at, two_factor_temp_secret_created_at
         FROM users
         WHERE email = :email
         LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();

    return is_array($user) ? $user : null;
}

function find_user_by_id(int $id): ?array
{
    $statement = db()->prepare(
        'SELECT id, name, email, created_at, email_verified_at,
                two_factor_enabled, two_factor_confirmed_at
         FROM users
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $id]);
    $user = $statement->fetch();

    return is_array($user) ? $user : null;
}

function find_user_auth_by_id(int $id): ?array
{
    $statement = db()->prepare(
        'SELECT id, name, email, password_hash, created_at,
                two_factor_enabled, two_factor_secret_encrypted,
                two_factor_temp_secret_encrypted,
                two_factor_confirmed_at, two_factor_temp_secret_created_at
         FROM users
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $id]);
    $user = $statement->fetch();

    return is_array($user) ? $user : null;
}

function create_user(string $name, string $email, string $password): int
{
    $statement = db()->prepare(
        'INSERT INTO users (name, email, password_hash, lgpd_consent_at)
        VALUES (:name, :email, :password_hash, :lgpd_consent_at)'
    );
    $statement->execute([
        'name' => $name,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'lgpd_consent_at' => date('Y-m-d H:i:s'),

    ]);

    return (int) db()->lastInsertId();
}

function create_raw_token(): string
{
    return bin2hex(random_bytes(32));
}

function hash_raw_token(string $rawToken): string
{
    return hash('sha256', $rawToken);
}

function store_email_verification_token(\PDO $pdo, int $userId): string
{
    $rawToken = create_raw_token();
    $tokenHash = hash_raw_token($rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + 86400);

    $deleteStatement = $pdo->prepare('DELETE FROM email_verification_tokens WHERE user_id = :user_id');
    $deleteStatement->execute(['user_id' => $userId]);

    $insertStatement = $pdo->prepare(
        'INSERT INTO email_verification_tokens (user_id, token_hash, expires_at)
         VALUES (:user_id, :token_hash, :expires_at)'
    );
    $insertStatement->execute([
        'user_id' => $userId,
        'token_hash' => $tokenHash,
        'expires_at' => $expiresAt,
    ]);

    return $rawToken;
}

function register_user_and_send_verification_email(string $name, string $email, string $password): int
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $userId = create_user($name, $email, $password);
        $token = store_email_verification_token($pdo, $userId);

        send_email_verification_email([
            'id' => $userId,
            'name' => $name,
            'email' => $email,
        ], $token);

        $pdo->commit();
    } catch (\Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $throwable;
    }

    return $userId;
}

function send_fresh_email_verification_link(array $user): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $token = store_email_verification_token($pdo, (int) $user['id']);
        send_email_verification_email($user, $token);
        $pdo->commit();
    } catch (\Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $throwable;
    }
}

function login_user(array $user): array
{
    ensure_session_started();
    clear_two_factor_pending();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    unset($_SESSION['csrf_token']);

    return user_public_data($user);
}

function logout_user(): void
{
    ensure_session_started();

    clear_two_factor_pending();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 3600,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }

    session_destroy();
    session_start();
    session_regenerate_id(true);
}

function current_user(): ?array
{
    ensure_session_started();

    $userId = $_SESSION['user_id'] ?? null;

    if (!is_int($userId) && !ctype_digit((string) $userId)) {
        return null;
    }

    $user = find_user_by_id((int) $userId);

    if ($user === null) {
        unset($_SESSION['user_id']);
        return null;
    }

    return user_public_data($user);
}

function verify_current_password(int $userId, string $password): bool
{
    $user = find_user_auth_by_id($userId);

    if ($user === null) {
        return false;
    }

    return password_verify($password, (string) $user['password_hash']);
}

function start_two_factor_pending(int $userId): void
{
    ensure_session_started();

    unset($_SESSION['user_id']);
    $_SESSION['two_factor_pending'] = [
        'user_id' => $userId,
        'started_at' => time(),
        'expires_at' => time() + TWO_FACTOR_PENDING_TTL,
        'attempts' => 0,
    ];
}

function get_two_factor_pending(): ?array
{
    ensure_session_started();

    $pending = $_SESSION['two_factor_pending'] ?? null;

    if (!is_array($pending)) {
        return null;
    }

    $userId = $pending['user_id'] ?? null;
    $expiresAt = (int) ($pending['expires_at'] ?? 0);
    $attempts = (int) ($pending['attempts'] ?? 0);

    if ((!is_int($userId) && !ctype_digit((string) $userId)) || $expiresAt < time()) {
        clear_two_factor_pending();
        return null;
    }

    if ($attempts >= TWO_FACTOR_PENDING_MAX_ATTEMPTS) {
        clear_two_factor_pending();
        return null;
    }

    return [
        'user_id' => (int) $userId,
        'started_at' => (int) ($pending['started_at'] ?? 0),
        'expires_at' => $expiresAt,
        'attempts' => $attempts,
    ];
}

function increment_two_factor_pending_attempts(): int
{
    ensure_session_started();

    if (!isset($_SESSION['two_factor_pending']) || !is_array($_SESSION['two_factor_pending'])) {
        return 0;
    }

    $_SESSION['two_factor_pending']['attempts'] = (int) ($_SESSION['two_factor_pending']['attempts'] ?? 0) + 1;

    return (int) $_SESSION['two_factor_pending']['attempts'];
}

function clear_two_factor_pending(): void
{
    unset($_SESSION['two_factor_pending']);
}

function create_password_reset_token(int $userId): string
{
    $rawToken = create_raw_token();
    $tokenHash = hash_raw_token($rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);
    $pdo = db();

    $pdo->beginTransaction();

    try {
        $deleteStatement = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id');
        $deleteStatement->execute(['user_id' => $userId]);

        $insertStatement = $pdo->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
             VALUES (:user_id, :token_hash, :expires_at)'
        );
        $insertStatement->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);

        $pdo->commit();
    } catch (\Throwable $throwable) {
        $pdo->rollBack();
        throw $throwable;
    }

    return $rawToken;
}

function find_password_reset_token(string $rawToken): ?array
{
    if ($rawToken === '') {
        return null;
    }

    $statement = db()->prepare(
        'SELECT prt.id, prt.user_id, prt.expires_at, prt.used_at, u.name, u.email
         FROM password_reset_tokens prt
         INNER JOIN users u ON u.id = prt.user_id
         WHERE prt.token_hash = :token_hash
         LIMIT 1'
    );
    $statement->execute([
        'token_hash' => hash_raw_token($rawToken),
    ]);
    $record = $statement->fetch();

    if (!is_array($record)) {
        return null;
    }

    if ($record['used_at'] !== null) {
        return null;
    }

    if (strtotime((string) $record['expires_at']) < time()) {
        return null;
    }

    return $record;
}

function invalidate_user_reset_tokens(int $userId): void
{
    $statement = db()->prepare(
        'UPDATE password_reset_tokens
         SET used_at = NOW()
         WHERE user_id = :user_id
           AND used_at IS NULL'
    );
    $statement->execute(['user_id' => $userId]);
}

function find_email_verification_token_record(string $rawToken): ?array
{
    if ($rawToken === '') {
        return null;
    }

    $statement = db()->prepare(
        'SELECT evt.id, evt.user_id, evt.expires_at, evt.used_at, u.name, u.email, u.email_verified_at
         FROM email_verification_tokens evt
         INNER JOIN users u ON u.id = evt.user_id
         WHERE evt.token_hash = :token_hash
         LIMIT 1'
    );
    $statement->execute([
        'token_hash' => hash_raw_token($rawToken),
    ]);
    $record = $statement->fetch();

    return is_array($record) ? $record : null;
}

function invalidate_user_email_verification_tokens(int $userId): void
{
    $statement = db()->prepare(
        'UPDATE email_verification_tokens
         SET used_at = NOW()
         WHERE user_id = :user_id
           AND used_at IS NULL'
    );
    $statement->execute(['user_id' => $userId]);
}

function mark_user_email_as_verified(int $userId): void
{
    $statement = db()->prepare(
        'UPDATE users
         SET email_verified_at = COALESCE(email_verified_at, NOW()),
             updated_at = NOW()
         WHERE id = :id'
    );
    $statement->execute(['id' => $userId]);
}

function update_user_password(int $userId, string $password): void
{
    $statement = db()->prepare(
        'UPDATE users
         SET password_hash = :password_hash,
             updated_at = NOW()
         WHERE id = :id'
    );
    $statement->execute([
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'id' => $userId,
    ]);
}

function find_admin_by_email(string $email): ?array
{
    $statement = db()->prepare(
        'SELECT id, name, email, created_at FROM admins WHERE email = :email LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $admin = $statement->fetch();

    return is_array($admin) ? $admin : null;
}

function find_admin_by_id(int $id): ?array
{
    $statement = db()->prepare(
        'SELECT id, name, email, created_at FROM admins WHERE id = :id LIMIT 1'
    );
    $statement->execute(['id' => $id]);
    $admin = $statement->fetch();

    return is_array($admin) ? $admin : null;
}

function create_admin_login_token(int $adminId): string
{
    $rawToken = create_raw_token();
    $tokenHash = hash_raw_token($rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + 900); // 15 minutes
    $pdo = db();

    $pdo->prepare('DELETE FROM admin_login_tokens WHERE admin_id = :admin_id')
        ->execute(['admin_id' => $adminId]);

    $pdo->prepare(
        'INSERT INTO admin_login_tokens (admin_id, token_hash, expires_at)
         VALUES (:admin_id, :token_hash, :expires_at)'
    )->execute([
        'admin_id' => $adminId,
        'token_hash' => $tokenHash,
        'expires_at' => $expiresAt,
    ]);

    return $rawToken;
}

function find_admin_login_token(string $rawToken): ?array
{
    if ($rawToken === '') {
        return null;
    }

    $statement = db()->prepare(
        'SELECT alt.id, alt.admin_id, alt.expires_at, alt.used_at, a.name, a.email
         FROM admin_login_tokens alt
         INNER JOIN admins a ON a.id = alt.admin_id
         WHERE alt.token_hash = :token_hash
         LIMIT 1'
    );
    $statement->execute(['token_hash' => hash_raw_token($rawToken)]);
    $record = $statement->fetch();

    if (!is_array($record)) {
        return null;
    }

    if ($record['used_at'] !== null) {
        return null;
    }

    if (strtotime((string) $record['expires_at']) < time()) {
        return null;
    }

    return $record;
}

function consume_admin_login_token(int $tokenId): void
{
    db()->prepare('UPDATE admin_login_tokens SET used_at = NOW() WHERE id = :id')
        ->execute(['id' => $tokenId]);
}

function login_admin(array $admin): array
{
    ensure_session_started();
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['id'];
    unset($_SESSION['csrf_token']);

    return [
        'id'         => (int) $admin['id'],
        'name'       => (string) $admin['name'],
        'email'      => (string) $admin['email'],
        'created_at' => $admin['created_at'] ?? null,
    ];
}

function current_admin(): ?array
{
    ensure_session_started();

    $adminId = $_SESSION['admin_id'] ?? null;

    if (!is_int($adminId) && !ctype_digit((string) $adminId)) {
        return null;
    }

    $admin = find_admin_by_id((int) $adminId);

    if ($admin === null) {
        unset($_SESSION['admin_id']);
        return null;
    }

    return [
        'id'         => (int) $admin['id'],
        'name'       => (string) $admin['name'],
        'email'      => (string) $admin['email'],
        'created_at' => $admin['created_at'] ?? null,
    ];
}

function require_admin(): array
{
    $admin = current_admin();

    if ($admin === null) {
        error_response('Acesso restrito.', [], 401);
    }

    return $admin;
}

function logout_admin(): void
{
    ensure_session_started();
    unset($_SESSION['admin_id'], $_SESSION['csrf_token']);
    session_regenerate_id(true);
}

function two_factor_status_array(array $user): array
{
    $userId = (int) ($user['id'] ?? 0);

    return [
        'enabled'                => (bool) ($user['two_factor_enabled'] ?? false),
        'confirmed'              => !empty($user['two_factor_confirmed_at']),
        'confirmed_at'           => $user['two_factor_confirmed_at'] ?? null,
        'setup_pending'          => !empty($user['two_factor_temp_secret_encrypted']),
        'backup_codes_remaining' => $userId > 0 ? count_remaining_backup_codes($userId) : 0,
    ];
}
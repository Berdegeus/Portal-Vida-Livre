CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email_verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_email_verification_tokens_token_hash (token_hash),
    KEY idx_email_verification_tokens_user_id (user_id),
    KEY idx_email_verification_tokens_expires_at (expires_at),
    CONSTRAINT fk_email_verification_tokens_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS directory_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(160) NOT NULL,
    entry_type ENUM('professional', 'clinic', 'support_group') NOT NULL,
    name VARCHAR(160) NOT NULL,
    specialty VARCHAR(160) NOT NULL,
    city VARCHAR(120) NOT NULL,
    state CHAR(2) NOT NULL,
    service_mode ENUM('online', 'presencial', 'hibrido') NOT NULL DEFAULT 'presencial',
    short_bio TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_directory_entries_slug (slug),
    KEY idx_directory_entries_type (entry_type),
    KEY idx_directory_entries_specialty (specialty),
    KEY idx_directory_entries_city_state (city, state),
    KEY idx_directory_entries_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_directory_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    directory_entry_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_directory_subscriptions_unique (user_id, directory_entry_id),
    KEY idx_user_directory_subscriptions_user (user_id),
    KEY idx_user_directory_subscriptions_entry (directory_entry_id),
    CONSTRAINT fk_user_directory_subscriptions_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_user_directory_subscriptions_entry
        FOREIGN KEY (directory_entry_id) REFERENCES directory_entries(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS totp_secrets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    secret VARCHAR(255) NOT NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_totp_secrets_user_id (user_id),
    CONSTRAINT fk_totp_secrets_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER updated_at;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS two_factor_secret_encrypted TEXT NULL AFTER two_factor_enabled;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS two_factor_temp_secret_encrypted TEXT NULL AFTER two_factor_secret_encrypted;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS two_factor_confirmed_at DATETIME NULL AFTER two_factor_temp_secret_encrypted;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS two_factor_temp_secret_created_at DATETIME NULL AFTER two_factor_confirmed_at;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS lgpd_consent_at DATETIME NULL AFTER email_verified_at;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_password_reset_tokens_token_hash (token_hash),
    KEY idx_password_reset_tokens_user_id (user_id),
    KEY idx_password_reset_tokens_expires_at (expires_at),
    CONSTRAINT fk_password_reset_tokens_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_backup_codes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_backup_codes_user_id (user_id),
    CONSTRAINT fk_user_backup_codes_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_login_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_admin_login_tokens_token_hash (token_hash),
    KEY idx_admin_login_tokens_admin_id (admin_id),
    KEY idx_admin_login_tokens_expires_at (expires_at),
    CONSTRAINT fk_admin_login_tokens_admin
        FOREIGN KEY (admin_id) REFERENCES admins(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



ALTER TABLE admins
    ADD COLUMN IF NOT EXISTS telegram_chat_id BIGINT NULL;

CREATE TABLE IF NOT EXISTS telegram_codigos (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id  BIGINT UNSIGNED NOT NULL,
    codigo    VARCHAR(6) NOT NULL,
    tipo      ENUM('vinculacao','login') NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usado     TINYINT(1) NOT NULL DEFAULT 0,
    KEY idx_tc_admin_tipo (admin_id, tipo),
    CONSTRAINT fk_tc_admin
        FOREIGN KEY (admin_id) REFERENCES admins(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

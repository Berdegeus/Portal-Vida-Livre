# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Portal Vida Livre is a mental health professional directory platform built with vanilla PHP and JavaScript — no framework on either side.

## Running Locally

```bash
cd backend && composer install && cd ..
php serve.php
# App runs at http://localhost:APP_PORT (default 8000)
```

`serve.php` connects to the DB, runs `schema.sql` (idempotent `CREATE TABLE IF NOT EXISTS`) and `seed.sql`, then starts PHP's built-in server with the project root as docroot.

Copy `backend/.env.example` to `backend/.env` and fill in DB and SMTP credentials before starting.

**No automated test suite.** Testing is manual via the frontend HTML forms or direct API calls (JSON or form-encoded).

## Architecture

### Request flow

```
Static HTML (frontend/*.html)
  → frontend/assets/js/api.js  (fetch wrapper; injects CSRF token, parses envelope)
  → backend/api/*.php           (one file per endpoint; handles CSRF, business logic, DB)
  → JSON response: { success, message, data, errors }
```

### Backend layout

- `backend/core/bootstrap.php` — required by every API endpoint; loads all core modules
- `backend/core/db.php` — singleton PDO connection (UTF-8mb4)
- `backend/core/auth.php` — session management, login state helpers
- `backend/core/crypto.php` — `password_hash` wrappers + symmetric encryption for 2FA secrets
- `backend/core/totp.php` — TOTP setup, QR code generation, backup codes
- `backend/core/csrf.php` — token generation and validation; rotated after each request
- `backend/core/mailer.php` — PHPMailer + SMTP; configured via `backend/config/mail.php`
- `backend/core/helpers.php` — config loading, request parsing, input validation utilities
- `backend/core/response.php` — `json_success()` / `json_error()` response helpers
- `backend/api/*.php` — one endpoint per file (register, login, logout, two-factor-*, directory-search, store, etc.)

Configuration files in `backend/config/` read from `.env` via `backend/core/env.php`. A `.local.php` override is supported for local dev.

### Frontend layout

- `frontend/*.html` — one page per feature; no templating, pure static HTML
- `frontend/assets/js/api.js` — centralized fetch wrapper; reads CSRF token from `sessionStorage`, handles 401 redirects and error envelope
- `frontend/assets/js/internal-ui.js` — shared modal and notification primitives
- Page-specific JS files (`login.js`, `cadastro.js`, `two-factor.js`, `security.js`, etc.) import from `api.js` and `internal-ui.js`

### Database

Key tables: `users`, `directory_entries`, `user_directory_subscriptions`, `email_verification_tokens`, `password_reset_tokens`, `totp_secrets`, `user_backup_codes`.

All queries use PDO prepared statements. Schema lives in `backend/database/schema.sql`.

### Authentication & 2FA

- Session cookies: `httpOnly`, `Secure`, `SameSite=Strict`
- CSRF token stored server-side in `$_SESSION`; rotated each request
- Email verification and password reset use expiring tokens (single-use)
- TOTP 2FA via `spomky-labs/otphp`; secrets stored encrypted with `APP_KEY`; backup codes hashed in `user_backup_codes`

## Key Conventions

- **Procedural, not OOP** — utilities are plain functions; no classes except Composer libraries
- **One endpoint per file** — each `backend/api/*.php` echoes JSON and exits
- **Every state-changing endpoint** must call the CSRF validation helper from `csrf.php` at the top
- **Password rules** — 12+ chars, uppercase, lowercase, digit, special character (enforced in both frontend `validation.js` and backend `helpers.php`)
- **LGPD compliance** — consent timestamp recorded at registration; users can request account deletion via `solicitar-exclusao.php`

<?php

declare(strict_types=1);

function load_config(string $name): array
{
    static $configs = [];

    if (isset($configs[$name])) {
        return $configs[$name];
    }

    $basePath = dirname(__DIR__);
    $localPath = $basePath . '/config/' . $name . '.local.php';
    $defaultPath = $basePath . '/config/' . $name . '.php';
    $path = file_exists($localPath) ? $localPath : $defaultPath;

    if (!file_exists($path)) {
        throw new \RuntimeException('Arquivo de configuracao ausente: ' . $name);
    }

    $config = require $path;

    if (!is_array($config)) {
        throw new \RuntimeException('Configuracao invalida: ' . $name);
    }

    $configs[$name] = $config;

    return $configs[$name];
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function request_data(): array
{
    static $data = null;

    if (is_array($data)) {
        return $data;
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw ?: '[]', true);
        $data = is_array($decoded) ? $decoded : [];

        return $data;
    }

    $data = $_POST;

    return is_array($data) ? $data : [];
}

function normalize_email(string $email): string
{
    $email = trim($email);

    return function_exists('mb_strtolower') ? mb_strtolower($email) : strtolower($email);
}

function sanitize_name(string $name): string
{
    $name = strip_tags($name);
    $name = preg_replace('/\s+/', ' ', trim($name)) ?? trim($name);

    return $name;
}

function string_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function password_strength_errors(string $password, string $name = ''): array
{
    $errors = [];

    if (string_length($password) < 12) {
        $errors[] = 'A senha deve ter pelo menos 12 caracteres.';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'A senha deve ter ao menos uma letra maiuscula.';
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'A senha deve ter ao menos uma letra minuscula.';
    }

    if (!preg_match('/\d/', $password)) {
        $errors[] = 'A senha deve ter ao menos um numero.';
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'A senha deve ter ao menos um caractere especial.';
    }

    if ($name !== '' && mb_stripos($password, $name) !== false) {
        $errors[] = 'A senha nao pode conter seu nome.';
    }

    return $errors;
}

function add_error(array &$errors, string $field, string $message): void
{
    if (!isset($errors[$field]) || !is_array($errors[$field])) {
        $errors[$field] = [];
    }

    $errors[$field][] = $message;
}

function has_errors(array $errors): bool
{
    return $errors !== [];
}

function is_secure_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }

    return false;
}

function current_base_url(): string
{
    $scheme = is_secure_request() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';

    return $scheme . '://' . $host;
}

function frontend_url(string $path = ''): string
{
    $base = rtrim(current_base_url(), '/') . '/frontend';

    if ($path === '') {
        return $base;
    }

    return $base . '/' . ltrim($path, '/');
}


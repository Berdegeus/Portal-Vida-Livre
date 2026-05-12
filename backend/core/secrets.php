<?php

declare(strict_types=1);

function secret_config(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $config = load_config('secrets');

    return $config;
}

function secret_value(string $name, ?string $default = null): ?string
{
    $value = env($name);

    if ($value !== null && $value !== '') {
        return $value;
    }

    $obfuscated = env($name . '_OBFUSCATED');

    if ($obfuscated !== null && $obfuscated !== '') {
        return resolve_obfuscated_secret($obfuscated);
    }

    return $default;
}

function required_secret(string $name): string
{
    $value = secret_value($name);

    if ($value === null || $value === '') {
        throw new \RuntimeException('Segredo obrigatorio ausente: ' . $name);
    }

    assert_secret_not_placeholder($name, $value);

    return $value;
}

function assert_secret_not_placeholder(string $name, string $value): void
{
    $config = secret_config();
    $placeholders = $config['placeholder_markers'] ?? [];
    $lowerValue = strtolower($value);

    foreach ($placeholders as $marker) {
        $marker = strtolower((string) $marker);

        if ($marker !== '' && str_contains($lowerValue, $marker)) {
            throw new \RuntimeException('Segredo obrigatorio parece placeholder: ' . $name);
        }
    }
}

function resolve_obfuscated_secret(string $encoded): string
{
    $prefix = 'words:v1:';

    if (!str_starts_with($encoded, $prefix)) {
        throw new \RuntimeException('Formato de segredo ofuscado invalido.');
    }

    $rawIndexes = substr($encoded, strlen($prefix));

    if ($rawIndexes === '') {
        throw new \RuntimeException('Mapa de segredo ofuscado vazio.');
    }

    $words = secret_base_words();
    $parts = array_map('trim', explode(',', $rawIndexes));
    $secret = '';

    foreach ($parts as $part) {
        if (!preg_match('/^\d+$/', $part)) {
            throw new \RuntimeException('Indice de segredo ofuscado invalido.');
        }

        $index = (int) $part;

        if ($index < 1 || $index > count($words)) {
            throw new \RuntimeException('Indice de segredo ofuscado fora do texto-base.');
        }

        $secret .= $words[$index - 1];
    }

    return $secret;
}

function secret_base_words(): array
{
    static $words = null;

    if (is_array($words)) {
        return $words;
    }

    $path = dirname(__DIR__) . '/resources/secret-base.txt';
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new \RuntimeException('Texto-base de segredos ausente.');
    }

    $words = preg_split('/\s+/', trim($contents)) ?: [];
    $words = array_values(array_filter($words, static fn (string $word): bool => $word !== ''));

    if ($words === []) {
        throw new \RuntimeException('Texto-base de segredos vazio.');
    }

    return $words;
}

function validate_local_secrets(): void
{
    $config = secret_config();
    $required = $config['required_local'] ?? [];

    foreach ($required as $name) {
        required_secret((string) $name);
    }
}

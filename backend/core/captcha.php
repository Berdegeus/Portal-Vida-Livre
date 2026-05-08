<?php

declare(strict_types=1);

const CAPTCHA_TTL         = 600;
const CAPTCHA_LENGTH      = 5;
const CAPTCHA_SESSION_KEY = 'captcha';

function generate_captcha_code(): string
{
    $code = '';
    for ($i = 0; $i < CAPTCHA_LENGTH; $i++) {
        $code .= (string) random_int(0, 9);
    }
    $now = time();
    $_SESSION[CAPTCHA_SESSION_KEY] = [
        'code'       => $code,
        'created_at' => $now,
        'expires_at' => $now + CAPTCHA_TTL,
    ];
    return $code;
}

function get_captcha_code(): ?string
{
    $c = $_SESSION[CAPTCHA_SESSION_KEY] ?? null;
    if (!is_array($c) || (int) ($c['expires_at'] ?? 0) < time()) {
        return null;
    }
    $code = $c['code'] ?? '';
    return (is_string($code) && $code !== '') ? $code : null;
}

function clear_captcha(): void
{
    unset($_SESSION[CAPTCHA_SESSION_KEY]);
}

function require_captcha(): void
{
    $answer = trim((string) (request_data()['captcha_answer'] ?? ''));
    $entry  = $_SESSION[CAPTCHA_SESSION_KEY] ?? null;

    if (!is_array($entry)) {
        clear_captcha();
        error_response(
            'Codigo de verificacao nao carregado.',
            ['captcha' => ['Codigo de verificacao nao carregado.']],
            422
        );
    }

    $expired = (int) ($entry['expires_at'] ?? 0) < time();
    clear_captcha();

    if ($expired) {
        error_response(
            'O codigo expirou. Recarregue a imagem.',
            ['captcha' => ['O codigo expirou. Recarregue a imagem.']],
            422
        );
    }

    if ($answer === '' || !hash_equals((string) ($entry['code'] ?? ''), $answer)) {
        error_response(
            'Codigo de verificacao incorreto.',
            ['captcha' => ['Codigo de verificacao incorreto.']],
            422
        );
    }
}

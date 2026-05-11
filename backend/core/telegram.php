<?php

declare(strict_types=1);

function telegram_bot_token(): string
{
    $config = load_config('telegram');
    $token  = (string) ($config['bot_token'] ?? '');

    if ($token === '') {
        throw new \RuntimeException('TELEGRAM_BOT_TOKEN nao configurado no .env.');
    }

    return $token;
}

function telegram_bot_username(): string
{
    $config = load_config('telegram');
    return (string) ($config['bot_username'] ?? '');
}

/**
 * GET simples — usado pelo bot (getUpdates).
 */
function telegram_get(string $method, array $params = []): array
{
    $url = 'https://api.telegram.org/bot' . telegram_bot_token() . '/' . $method;

    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    $context  = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        throw new \RuntimeException('Falha ao contactar o Telegram (GET).');
    }

    $data = json_decode($response, true);

    if (!is_array($data) || !($data['ok'] ?? false)) {
        throw new \RuntimeException('Telegram API: ' . ($data['description'] ?? 'erro desconhecido'));
    }

    return is_array($data['result']) ? $data['result'] : [];
}

/**
 * POST com JSON — usado para sendMessage (acentos funcionam corretamente via POST).
 */
function telegram_post(string $method, array $payload): array
{
    $url  = 'https://api.telegram.org/bot' . telegram_bot_token() . '/' . $method;
    $body = (string) json_encode($payload, JSON_UNESCAPED_UNICODE);

    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n",
            'content'       => $body,
            'ignore_errors' => true,
        ],
        'ssl'  => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        throw new \RuntimeException('Falha ao contactar o Telegram (POST).');
    }

    $data = json_decode($response, true);

    if (!is_array($data) || !($data['ok'] ?? false)) {
        throw new \RuntimeException('Telegram API: ' . ($data['description'] ?? 'erro desconhecido'));
    }

    return is_array($data['result']) ? $data['result'] : [];
}

function telegram_send_message(int $chatId, string $text): void
{
    telegram_post('sendMessage', ['chat_id' => $chatId, 'text' => $text]);
}

function telegram_get_updates(int $offset = 0): array
{
    $params = ['timeout' => 0];

    if ($offset > 0) {
        $params['offset'] = $offset;
    }

    return telegram_get('getUpdates', $params);
}

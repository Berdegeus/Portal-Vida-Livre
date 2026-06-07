<?php

/**
 * Bot de Telegram — script persistente para vinculação de admins.
 *
 * Execute em um terminal separado:
 *   php backend/bot/telegram-bot.php
 *
 * Mantém em loop, buscando atualizações a cada 1 segundo.
 * Processa apenas mensagens de vinculação (código de 4 dígitos).
 * Códigos de login (6 dígitos) são validados exclusivamente pelo site.
 */

declare(strict_types=1);

// Garante que o script rode da raiz do projeto
chdir(dirname(__DIR__, 2));

require_once __DIR__ . '/../core/bootstrap.php';

echo "[bot] Iniciado. Aguardando mensagens...\n";

function bot_mask_chat_id(int $chatId): string
{
    $value = (string) $chatId;

    if (strlen($value) <= 4) {
        return '****';
    }

    return str_repeat('*', strlen($value) - 4) . substr($value, -4);
}

function bot_handle_user_vinculacao(int $chatId, string $codigo): void
{
    $record = find_user_telegram_codigo('V', $codigo);

    if ($record === null) {
        telegram_send_message($chatId, "Código inválido ou expirado. Solicite um novo cadastrando-se novamente.");
        echo "[bot] Codigo de vinculacao de usuario invalido ou expirado.\n";
        return;
    }

    mark_user_telegram_codigo_usado((int) $record['id']);
    link_user_telegram((int) $record['user_id'], $chatId);
    mark_user_email_as_verified((int) $record['user_id']);

    telegram_send_message(
        $chatId,
        "Email verificado com sucesso! Bem-vindo ao Portal Vida Livre, {$record['name']}.\n\n" .
        "Sua conta está ativa e este Telegram está vinculado para notificações."
    );
    echo "[bot] Usuario id={$record['user_id']} verificado e vinculado ao Telegram.\n";
}

function bot_handle_user_reset(int $chatId, string $codigo): void
{
    $record = find_user_telegram_codigo('R', $codigo);

    if ($record === null) {
        telegram_send_message($chatId, "Link de redefinição inválido ou expirado. Solicite um novo em 'Esqueci minha senha'.");
        echo "[bot] Codigo de reset de usuario invalido ou expirado.\n";
        return;
    }

    $rawToken = (string) $record['extra_data'];
    $resetUrl = frontend_url('redefinir-senha.html?token=' . urlencode($rawToken));

    mark_user_telegram_codigo_usado((int) $record['id']);
    link_user_telegram((int) $record['user_id'], $chatId);

    telegram_send_message(
        $chatId,
        "Redefinição de senha — Portal Vida Livre\n\n" .
        "Clique no link abaixo para criar uma nova senha:\n{$resetUrl}\n\nO link expira em 1 hora."
    );
    echo "[bot] Link de reset enviado ao usuario id={$record['user_id']}.\n";
}

$lastUpdateId = 0;
$iteracoes    = 0;

while (true) {
    try {
        $updates = telegram_get_updates($lastUpdateId > 0 ? $lastUpdateId + 1 : 0);

        foreach ($updates as $update) {
            $lastUpdateId = max($lastUpdateId, (int) ($update['update_id'] ?? 0));

            $message = $update['message'] ?? null;
            if (!is_array($message)) {
                continue;
            }

            $chatId = (int) ($message['chat']['id'] ?? 0);
            $texto  = trim((string) ($message['text'] ?? ''));

            if ($chatId === 0 || $texto === '') {
                continue;
            }

            echo '[bot] Mensagem recebida chat_id=' . bot_mask_chat_id($chatId) . "\n";

            // /start com payload de usuário
            if (preg_match('/^\/start\s+([VR])_(\d{6})$/', $texto, $m)) {
                $prefix = $m[1];
                $codigo = $m[2];

                if ($prefix === 'V') {
                    bot_handle_user_vinculacao($chatId, $codigo);
                } else {
                    bot_handle_user_reset($chatId, $codigo);
                }
                continue;
            }

            // /start sem payload
            if ($texto === '/start') {
                $botUsername = telegram_bot_username();
                telegram_send_message(
                    $chatId,
                    "Olá! Sou o bot do Portal Vida Livre.\n\n" .
                    "Sou usado para:\n" .
                    "• Verificar seu email ao criar uma conta\n" .
                    "• Enviar links de redefinição de senha\n" .
                    "• Autenticar administradores (2FA)\n\n" .
                    "Use os links enviados pelo site para interagir comigo."
                );
                continue;
            }

            // Código de 4 dígitos: vinculação de admin (fluxo existente)
            if (preg_match('/^\d{4}$/', $texto)) {
                $record = find_telegram_codigo_vinculacao($texto);

                if ($record === null) {
                    telegram_send_message($chatId, "Código inválido ou expirado. Solicite um novo na tela de vinculação.");
                    echo "[bot] Codigo de vinculacao de admin invalido ou expirado.\n";
                } else {
                    link_admin_telegram((int) $record['admin_id'], $chatId);
                    marcar_codigo_usado((int) $record['id']);
                    telegram_send_message(
                        $chatId,
                        "Vinculação concluída! Bem-vindo, {$record['name']}.\n" .
                        "A partir de agora você receberá os códigos de acesso aqui."
                    );
                    echo "[bot] Admin id={$record['admin_id']} vinculado ao Telegram.\n";
                }
                continue;
            }

            telegram_send_message($chatId, "Não entendi. Use os links enviados pelo Portal Vida Livre para interagir comigo.");
        }

        $iteracoes++;
        if ($iteracoes % 60 === 0) {
            cleanup_user_telegram_codigos();
            cleanup_telegram_codigos();
        }
    } catch (\Throwable $e) {
        echo "[bot] Erro: " . $e->getMessage() . "\n";
        sleep(5);
        continue;
    }

    sleep(1);
}

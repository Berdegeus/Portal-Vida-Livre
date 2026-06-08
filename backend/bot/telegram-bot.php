<?php

/**
 * Bot de Telegram — script persistente.
 *
 * Execute em um terminal separado:
 *   php backend/bot/telegram-bot.php
 *
 * Mantém em loop, buscando atualizações a cada 1 segundo.
 * Fluxos tratados pelo bot:
 *   - V_XXXXXX  → vinculação de usuário (digitado ou via deep link)
 *   - R_XXXXXX  → reset de senha de usuário (digitado ou via deep link)
 *   - XXXX      → vinculação de admin (4 dígitos)
 *   - XXXXXX    → tentativa de código sem prefixo (orientação ao usuário)
 * Códigos de login OTP são validados exclusivamente pelo site.
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

            // Código de vinculação de usuário: V_XXXXXX digitado ou via deep link
            if (preg_match('/^(?:\/start\s+)?V_(\d{6})$/i', $texto, $m)) {
                bot_handle_user_vinculacao($chatId, $m[1]);
                continue;
            }

            // Código de reset de senha de usuário: R_XXXXXX digitado ou via deep link
            if (preg_match('/^(?:\/start\s+)?R_(\d{6})$/i', $texto, $m)) {
                bot_handle_user_reset($chatId, $m[1]);
                continue;
            }

            // /start sem payload
            if (strcasecmp($texto, '/start') === 0) {
                telegram_send_message(
                    $chatId,
                    "Olá! Sou o bot do Portal Vida Livre.\n\n" .
                    "Posso ajudar com:\n" .
                    "• Verificar sua conta ao se cadastrar (envie o código V_XXXXXX)\n" .
                    "• Redefinir sua senha (use o link enviado pelo site)\n" .
                    "• Autenticar administradores\n\n" .
                    "Use os links ou códigos exibidos no site para interagir comigo."
                );
                continue;
            }

            // Código de 4 dígitos: vinculação de admin
            if (preg_match('/^\d{4}$/', $texto)) {
                $record = find_telegram_codigo_vinculacao($texto);

                if ($record === null) {
                    telegram_send_message($chatId, "Código inválido ou expirado. Acesse o painel administrativo para gerar um novo código.");
                    echo "[bot] Codigo de vinculacao de admin invalido ou expirado.\n";
                } else {
                    link_admin_telegram((int) $record['admin_id'], $chatId);
                    marcar_codigo_usado((int) $record['id']);
                    telegram_send_message(
                        $chatId,
                        "Vinculação concluída! Bem-vindo, {$record['name']}.\n" .
                        "Retorne ao painel para concluir o acesso."
                    );
                    echo "[bot] Admin id={$record['admin_id']} vinculado ao Telegram.\n";
                }
                continue;
            }

            // Número de 6 dígitos sem prefixo — pode ser código de vinculação digitado errado
            if (preg_match('/^\d{6}$/', $texto)) {
                $record = find_user_telegram_codigo('V', $texto);
                if ($record !== null) {
                    bot_handle_user_vinculacao($chatId, $texto);
                } else {
                    telegram_send_message(
                        $chatId,
                        "Código não reconhecido.\n\n" .
                        "• Para verificar sua conta, envie o código no formato V_XXXXXX (ex.: V_123456).\n" .
                        "• Códigos de login são inseridos diretamente no site, não aqui."
                    );
                }
                continue;
            }

            telegram_send_message(
                $chatId,
                "Não reconheço essa mensagem.\n\n" .
                "• Para verificar sua conta: envie o código V_XXXXXX exibido no site.\n" .
                "• Para redefinir sua senha: use o link enviado pelo site.\n" .
                "• Para acessar o painel admin: use o código de 4 dígitos exibido no painel."
            );
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

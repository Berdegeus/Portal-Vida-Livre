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

$lastUpdateId = 0;

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

            echo "[bot] chat_id={$chatId} texto=\"{$texto}\"\n";

            if ($texto === '/start') {
                telegram_send_message(
                    $chatId,
                    "Olá! Este bot é usado para autenticação de administradores do Portal Vida Livre.\n\n" .
                    "Para vincular sua conta, envie o código de 4 dígitos exibido na tela de vinculação."
                );
                continue;
            }

            if (preg_match('/^\d{4}$/', $texto)) {
                $record = find_telegram_codigo_vinculacao($texto);

                if ($record === null) {
                    telegram_send_message($chatId, "Código inválido ou expirado. Solicite um novo na tela de vinculação.");
                    echo "[bot] Código de vinculação não encontrado: {$texto}\n";
                } else {
                    link_admin_telegram((int) $record['admin_id'], $chatId);
                    marcar_codigo_usado((int) $record['id']);
                    telegram_send_message(
                        $chatId,
                        "Vinculação concluída! Bem-vindo, {$record['name']}.\n" .
                        "A partir de agora você receberá os códigos de acesso aqui."
                    );
                    echo "[bot] Admin id={$record['admin_id']} ({$record['name']}) vinculado ao chat_id={$chatId}\n";
                }
                continue;
            }

            telegram_send_message($chatId, "Não entendi. Este bot é apenas para autenticação 2FA do painel.");
        }
    } catch (\Throwable $e) {
        echo "[bot] Erro: " . $e->getMessage() . "\n";
        sleep(5);
        continue;
    }

    sleep(1);
}

<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();

$data   = request_data();
$codigo = trim((string) ($data['codigo'] ?? ''));

if (strlen($codigo) !== 6 || !ctype_digit($codigo)) {
    error_response('Codigo invalido. Informe os 6 digitos do e-mail.', [
        'codigo' => ['Informe os 6 digitos do e-mail.'],
    ], 422);
}

$record = find_admin_login_token_by_codigo($codigo);

if ($record === null) {
    error_response('Codigo invalido ou expirado. Solicite um novo acesso.', [
        '_general' => ['Codigo invalido ou expirado.'],
    ], 401);
}

$admin = find_admin_full_by_id((int) $record['admin_id']);

if ($admin === null) {
    error_response('Administrador nao encontrado.', [], 500);
}

// Cria o código Telegram ANTES de consumir o token.
$temTelegram = $admin['telegram_chat_id'] !== null;
$tipo        = $temTelegram ? 'login' : 'vinculacao';
$codigoTelegram = create_telegram_codigo((int) $admin['id'], $tipo);

consume_admin_login_token((int) $record['id']);
start_admin_2fa_pending((int) $admin['id']);
cleanup_telegram_codigos();

if (!$temTelegram) {
    success_response('Vincule seu Telegram.', [
        'requires_2fa' => true,
        'step'         => 'vinculacao',
        'codigo'       => $codigoTelegram,
        'bot_username' => telegram_bot_username(),
        'csrf_token'   => rotate_csrf_token(),
    ]);
}

$chatId       = (int) $admin['telegram_chat_id'];
$simulateFail = !empty($_SESSION['admin_simulate_3p_fail']);
$telegramErro = null;

if ($simulateFail) {
    $telegramErro = true;
} else {
    try {
        telegram_send_message(
            $chatId,
            "Código de acesso ao Portal Vida Livre: {$codigoTelegram}\n\nVálido por 5 minutos. Não compartilhe."
        );
    } catch (\Throwable $e) {
        $telegramErro = true;
    }
}

if ($telegramErro !== null) {
    if (admin_has_security_questions((int) $admin['id'])) {
        if (is_admin_security_questions_locked((int) $admin['id'])) {
            $horas = (int) ceil(get_admin_security_question_lockout_seconds((int) $admin['id']) / 3600);
            error_response(
                "Telegram indisponivel e acesso via perguntas bloqueado. Tente novamente em {$horas} hora(s).",
                [],
                429
            );
        }

        start_admin_security_questions_pending((int) $admin['id'], 'telegram');
        success_response('Telegram indisponivel. Use as perguntas de seguranca para acessar.', [
            'requires_2fa' => true,
            'step'         => 'security_questions',
            'csrf_token'   => rotate_csrf_token(),
        ]);
    }

    error_response('Nao foi possivel enviar o codigo pelo Telegram. Tente novamente.', [], 503);
}

success_response('Codigo enviado pelo Telegram.', [
    'requires_2fa' => true,
    'step'         => 'login',
    'csrf_token'   => rotate_csrf_token(),
]);

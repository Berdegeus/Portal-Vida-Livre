<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();

$data  = request_data();
$email = normalize_email((string) ($data['email'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    error_response('Informe um e-mail valido.', ['email' => ['Informe um e-mail valido.']], 422);
}

// Resposta genérica para evitar enumeração de admins.
$admin = find_admin_by_email($email);

if ($admin !== null) {
    $adminId    = (int) $admin['id'];
    $adminFull  = find_admin_full_by_id($adminId);
    $simulateFail = !empty($_SESSION['admin_simulate_3p_fail']);
    $telegramSent = false;

    if (!$simulateFail && $adminFull !== null) {
        if (!empty($adminFull['telegram_chat_id'])) {
            // Admin já vinculado: envia código 6 dígitos diretamente via Telegram.
            $codigo = create_telegram_codigo($adminId, 'login');
            $sent   = notify_admin_login($adminFull, $codigo);

            if ($sent) {
                start_admin_2fa_pending($adminId);
                success_response(
                    'Código enviado ao seu Telegram. Insira-o abaixo para acessar o painel.',
                    ['step' => 'telegram_code', 'csrf_token' => rotate_csrf_token()]
                );
            }
        } else {
            // Admin sem Telegram vinculado: inicia vinculação direta.
            $codigo = create_telegram_codigo($adminId, 'vinculacao');
            start_admin_2fa_pending($adminId);
            success_response(
                'Para acessar o painel, vincule seu Telegram enviando o código abaixo para @' . telegram_bot_username() . '.',
                [
                    'step'         => 'vinculacao',
                    'codigo'       => $codigo,
                    'bot_username' => telegram_bot_username(),
                    'csrf_token'   => rotate_csrf_token(),
                ]
            );
        }
    }

    // Fallback: Telegram falhou ou simulação ativa.
    if (admin_has_security_questions($adminId)) {
        if (is_admin_security_questions_locked($adminId)) {
            $horas = (int) ceil(get_admin_security_question_lockout_seconds($adminId) / 3600);
            error_response(
                "Servico de Telegram indisponivel e acesso via perguntas bloqueado. Tente novamente em {$horas} hora(s).",
                [],
                429
            );
        }

        start_admin_security_questions_pending($adminId, 'telegram');
        success_response('Servico de Telegram indisponivel. Use as perguntas de seguranca para acessar.', [
            'fallback'   => 'security_questions',
            'csrf_token' => rotate_csrf_token(),
        ]);
    }
}

success_response('Se este e-mail estiver cadastrado como administrador, voce receberá instruções de acesso em instantes.', [
    'csrf_token' => rotate_csrf_token(),
]);

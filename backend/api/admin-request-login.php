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

$simulateFail = !empty($_SESSION['admin_simulate_3p_fail']);

// Always return the same success message to prevent email enumeration.
$admin = find_admin_by_email($email);

if ($admin !== null) {
    $emailSent = false;

    if ($simulateFail) {
        // Simulation mode: pretend the email failed without actually sending.
    } else {
        try {
            $tokenData = create_admin_login_token((int) $admin['id']);
            send_admin_magic_link_email($admin, $tokenData['token'], $tokenData['codigo']);
            $emailSent = true;
        } catch (\Throwable $e) {
            // Email send failed.
        }
    }

    if (!$emailSent && admin_has_security_questions((int) $admin['id'])) {
        if (is_admin_security_questions_locked((int) $admin['id'])) {
            $horas = (int) ceil(get_admin_security_question_lockout_seconds((int) $admin['id']) / 3600);
            error_response(
                "Servico de e-mail indisponivel e acesso via perguntas bloqueado. Tente novamente em {$horas} hora(s).",
                [],
                429
            );
        }

        start_admin_security_questions_pending((int) $admin['id'], 'email');
        success_response('Servico de e-mail indisponivel. Use as perguntas de seguranca para acessar.', [
            'fallback'   => 'security_questions',
            'csrf_token' => rotate_csrf_token(),
        ]);
    }
}

success_response('Se este e-mail estiver cadastrado como administrador, voce receberá um link de acesso em instantes.', [
    'csrf_token' => rotate_csrf_token(),
]);

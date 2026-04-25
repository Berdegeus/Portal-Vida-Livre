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

// Always return the same success message to prevent email enumeration.
$admin = find_admin_by_email($email);

if ($admin !== null) {
    try {
        $token = create_admin_login_token((int) $admin['id']);
        send_admin_magic_link_email($admin, $token);
    } catch (\Throwable $e) {
        // Silently fail — do not reveal whether the email exists.
    }
}

success_response('Se este e-mail estiver cadastrado como administrador, voce receberá um link de acesso em instantes.', [
    'csrf_token' => rotate_csrf_token(),
]);

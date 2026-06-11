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

// Always return success to prevent email enumeration.
$user  = find_user_by_email($email);
$notif = [];

if ($user !== null && !user_email_is_verified($user)) {
    try {
        $pdo       = db();
        $pdo->beginTransaction();
        $tokenData = store_email_verification_token($pdo, (int) $user['id']);
        $pdo->commit();
        $notif = notify_user_verification($user, $tokenData['token'], $tokenData['codigo']);
    } catch (\Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}

success_response('Se este e-mail tiver um cadastro pendente, enviaremos um novo codigo de confirmacao.', array_merge($notif, [
    'csrf_token' => rotate_csrf_token(),
]));

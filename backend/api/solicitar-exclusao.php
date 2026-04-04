<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();

$usuarioSessao = current_user();

if ($usuarioSessao === null) {
    error_response('Sessao invalida.', [], 401);
}

$dados = request_data();
$senha = (string) ($dados['password'] ?? '');
$errors = [];

if ($senha === '') {
    add_error($errors, 'password', 'Informe sua senha para confirmar a exclusao.');
}

if (has_errors($errors)) {
    error_response('Verifique os campos informados.', $errors, 422);
}

// Verifica se a senha informada ta correta antes de excluir a conta
if (!verify_current_password((int) $usuarioSessao['id'], $senha)) {
    add_error($errors, 'password', 'Senha incorreta.');
    error_response('Verifique os campos informados.', $errors, 422);
}

// Exclui o usuário -> o ON DELETE CASCADE no schema remove todos os dados relacionados:
// email_verification_tokens, password_reset_tokens, user_backup_codes,
// totp_secrets, user_directory_subscriptions
$statement = db()->prepare('DELETE FROM users WHERE id = :id');
$statement->execute(['id' => (int) $usuarioSessao['id']]);

// Encerra a sessão após excluir a conta
logout_user();

success_response('Sua conta foi excluida com sucesso.');
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

$dados             = request_data();
$senhaAtual        = (string) ($dados['current_password'] ?? '');
$novaSenha         = (string) ($dados['password'] ?? '');
$confirmacaoSenha  = (string) ($dados['password_confirmation'] ?? '');
$errors = [];

// Busca o usuário completo para validar nome e hash da senha atual
$user = find_user_auth_by_id((int) $usuarioSessao['id']);

if ($user === null) {
    error_response('Sessao invalida.', [], 401);
}

if ($senhaAtual === '') {
    add_error($errors, 'current_password', 'Informe sua senha atual.');
}

foreach (password_strength_errors($novaSenha, (string) ($user['name'] ?? '')) as $mensagem) {
    add_error($errors, 'password', $mensagem);
}

if ($confirmacaoSenha === '') {
    add_error($errors, 'password_confirmation', 'Confirme sua nova senha.');
} elseif (!hash_equals($novaSenha, $confirmacaoSenha)) {
    add_error($errors, 'password_confirmation', 'A confirmacao deve ser igual a nova senha.');
}

if (has_errors($errors)) {
    error_response('Verifique os campos informados.', $errors, 422);
}

if (!verify_current_password((int) $user['id'], $senhaAtual)) {
    add_error($errors, 'current_password', 'Senha atual incorreta.');
    error_response('Verifique os campos informados.', $errors, 422);
}

if (password_verify($novaSenha, (string) $user['password_hash'])) {
    add_error($errors, 'password', 'A nova senha nao pode ser igual a senha atual.');
    error_response('Verifique os campos informados.', $errors, 422);
}

try {
    update_user_password((int) $user['id'], $novaSenha);
} catch (\Throwable $throwable) {
    error_response('Nao foi possivel alterar a senha agora.', [], 500);
}

success_response('Senha alterada com sucesso.');
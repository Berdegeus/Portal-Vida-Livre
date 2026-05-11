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

$dados  = request_data();
$senha  = (string) ($dados['password'] ?? '');
$errors = [];

$removerInscricoes = ($dados['remover_inscricoes'] ?? '') === '1';
$remover2fa        = ($dados['remover_2fa'] ?? '') === '1';

if ($senha === '') {
    add_error($errors, 'password', 'Informe sua senha para confirmar a operacao.');
}

if (!$removerInscricoes && !$remover2fa) {
    add_error($errors, 'selecao', 'Selecione ao menos um item para remover.');
}

if (has_errors($errors)) {
    error_response('Verifique os campos informados.', $errors, 422);
}

if (!verify_current_password((int) $usuarioSessao['id'], $senha)) {
    add_error($errors, 'password', 'Senha incorreta.');
    error_response('Verifique os campos informados.', $errors, 422);
}

$pdo    = db();
$userId = (int) $usuarioSessao['id'];

try {
    $pdo->beginTransaction();

    if ($removerInscricoes) {
        $pdo->prepare('DELETE FROM user_directory_subscriptions WHERE user_id = :id')
            ->execute(['id' => $userId]);
    }

    if ($remover2fa) {
        $pdo->prepare('DELETE FROM totp_secrets WHERE user_id = :id')
            ->execute(['id' => $userId]);

        $pdo->prepare('DELETE FROM user_backup_codes WHERE user_id = :id')
            ->execute(['id' => $userId]);

        $pdo->prepare(
            'UPDATE users
             SET two_factor_enabled              = 0,
                 two_factor_secret_encrypted     = NULL,
                 two_factor_temp_secret_encrypted = NULL,
                 two_factor_confirmed_at         = NULL,
                 two_factor_temp_secret_created_at = NULL
             WHERE id = :id'
        )->execute(['id' => $userId]);
    }

    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_response('Nao foi possivel processar a solicitacao. Tente novamente.', [], 500);
}

success_response('Dados removidos com sucesso.');
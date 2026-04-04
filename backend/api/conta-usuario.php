<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'GET') {
    error_response('Metodo nao permitido.', [], 405);
}

$usuarioSessao = current_user();

if ($usuarioSessao === null) {
    error_response('Sessao invalida.', [], 401);
}

// Query própria para incluir lgpd_consent_at, que não estava sendo carregado em find_user_by_id
$statement = db()->prepare(
    'SELECT id, name, email, created_at, email_verified_at,
            lgpd_consent_at, two_factor_enabled, two_factor_confirmed_at
     FROM users
     WHERE id = :id
     LIMIT 1'
);
$statement->execute(['id' => (int) $usuarioSessao['id']]);
$usuario = $statement->fetch();

if (!is_array($usuario)) {
    error_response('Nao foi possivel carregar os dados da conta.', [], 500);
}

success_response('Dados da conta carregados com sucesso.', [
    'user' => [
        'id'                      => (int) $usuario['id'],
        'name'                    => (string) $usuario['name'],
        'email'                   => (string) $usuario['email'],
        'email_verified'          => !empty($usuario['email_verified_at']),
        'email_verified_at'       => $usuario['email_verified_at'] ?? null,
        'lgpd_consent_at'         => $usuario['lgpd_consent_at'] ?? null,
        'two_factor_enabled'      => (bool) ($usuario['two_factor_enabled'] ?? false),
        'two_factor_confirmed_at' => $usuario['two_factor_confirmed_at'] ?? null,
        'created_at'              => $usuario['created_at'] ?? null,
    ],
]);

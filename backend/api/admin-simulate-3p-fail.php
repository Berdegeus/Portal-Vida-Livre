<?php

declare(strict_types=1);

// Endpoint de desenvolvimento para simular falha dos serviços de terceiros
// (e-mail e Telegram) durante o fluxo de login administrativo.
//
// Uso:
//   GET /backend/api/admin-simulate-3p-fail.php          → mostra estado atual
//   GET /backend/api/admin-simulate-3p-fail.php?enable=1 → ativa simulação
//   GET /backend/api/admin-simulate-3p-fail.php?enable=0 → desativa simulação

require_once __DIR__ . '/../core/bootstrap.php';

ensure_session_started();

if (isset($_GET['enable'])) {
    $enable = (bool) (int) $_GET['enable'];

    if ($enable) {
        $_SESSION['admin_simulate_3p_fail'] = true;
        success_response('Simulacao ativada: e-mail e Telegram serao simulados como falhos.', [
            'active' => true,
        ]);
    } else {
        unset($_SESSION['admin_simulate_3p_fail']);
        success_response('Simulacao desativada: fluxo normal restaurado.', [
            'active' => false,
        ]);
    }
}

success_response('Estado atual da simulacao de falha de terceiros.', [
    'active' => !empty($_SESSION['admin_simulate_3p_fail']),
    'instrucoes' => [
        'ativar'    => '/backend/api/admin-simulate-3p-fail.php?enable=1',
        'desativar' => '/backend/api/admin-simulate-3p-fail.php?enable=0',
        'status'    => '/backend/api/admin-simulate-3p-fail.php',
    ],
]);

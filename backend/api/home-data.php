<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'GET') {
    error_response('Metodo nao permitido.', [], 405);
}

success_response('Dados da home carregados com sucesso.', [
    'stats' => directory_home_stats(),
    'metadata' => directory_metadata(),
]);

<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'GET') {
    error_response('Metodo nao permitido.', [], 405);
}

$specialty = trim((string) ($_GET['especialidade'] ?? ''));
$city = trim((string) ($_GET['cidade'] ?? ''));
$type = trim((string) ($_GET['tipo'] ?? ''));

try {
    success_response('Busca realizada com sucesso.', search_directory($specialty, $city, $type));
} catch (\Throwable $throwable) {
    error_response('Nao foi possivel realizar a busca agora.', [
        '_general' => ['Nao foi possivel realizar a busca agora.'],
    ], 500);
}

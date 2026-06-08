<?php
declare(strict_types=1);
require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'GET') {
    error_response('Metodo nao permitido.', [], 405);
}

try {
    $privateKey = load_rsa_private_key();
} catch (\RuntimeException $e) {
    error_response('Erro ao carregar chave privada.', [], 500);
}

$details = openssl_pkey_get_details($privateKey);
$publicKey = $details['key'];

success_response('Chave publica obtida.', ['public_key' => $publicKey]);
<?php
declare(strict_types=1);
require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'GET') {
    error_response('Metodo nao permitido.', [], 405);
}

$privateKeyPem = base64_decode(required_secret('RSA_PRIVATE_KEY'));
$privateKey = openssl_pkey_get_private($privateKeyPem);

if ($privateKey === false) {
    error_response('Erro ao carregar chave privada.', [], 500);
}

$details = openssl_pkey_get_details($privateKey);
$publicKey = $details['key'];

success_response('Chave publica obtida.', ['public_key' => $publicKey]);
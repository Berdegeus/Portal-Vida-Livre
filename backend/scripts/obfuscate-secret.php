<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Use este script apenas pela CLI.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/secrets.php';

fwrite(STDOUT, "Digite o segredo que deseja ofuscar: ");
$secret = fgets(STDIN);

if ($secret === false) {
    fwrite(STDERR, "Nao foi possivel ler o segredo informado.\n");
    exit(1);
}

$secret = rtrim($secret, "\r\n");
$secret = preg_replace('/^\xEF\xBB\xBF/', '', $secret) ?? $secret;
echo obfuscate_secret($secret) . PHP_EOL;

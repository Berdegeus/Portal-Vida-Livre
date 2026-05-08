<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'GET') {
    error_response('Metodo nao permitido.', [], 405);
}

$code = generate_captcha_code();

try {
    $captcha = new \Gregwar\Captcha\CaptchaBuilder($code);
    $captcha
        ->setDistortion(true)
        ->setMaxFrontLines(3)
        ->setMaxBehindLines(2)
        ->setTextColor(30, 30, 30)
        ->build(180, 60);
} catch (\Throwable $e) {
    error_log('[captcha-image] ' . $e->getMessage());
    http_response_code(500);
    exit;
}

header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$captcha->output();
exit;

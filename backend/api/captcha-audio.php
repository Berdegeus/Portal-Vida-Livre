<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'GET') {
    error_response('Metodo nao permitido.', [], 405);
}

$code = get_captcha_code();

if ($code === null) {
    error_response(
        'Codigo de verificacao nao disponivel.',
        ['captcha' => ['Recarregue a imagem antes de ouvir o audio.']],
        422
    );
}

$freqMap = [
    '0' => 261, '1' => 294, '2' => 330, '3' => 349, '4' => 392,
    '5' => 440, '6' => 494, '7' => 523, '8' => 587, '9' => 659,
];

$sampleRate    = 8000;
$toneSamples   = 4000;  // 500 ms
$gapSamples    = 2000;  // 250 ms
$leadinSamples = 4000;  // 500 ms

$pcm = str_repeat(chr(128), $leadinSamples);

foreach (str_split($code) as $digit) {
    $freq = $freqMap[$digit] ?? 440;

    for ($i = 0; $i < $toneSamples; $i++) {
        $s    = (int) round(128 + 100 * sin(2 * M_PI * $freq * $i / $sampleRate));
        $pcm .= chr(max(0, min(255, $s + rand(-15, 15))));
    }

    for ($i = 0; $i < $gapSamples; $i++) {
        $pcm .= chr(max(0, min(255, 128 + rand(-8, 8))));
    }
}

$dataSize = strlen($pcm);

$header  = 'RIFF';
$header .= pack('V', 36 + $dataSize);
$header .= 'WAVE';
$header .= 'fmt ';
$header .= pack('V', 16);
$header .= pack('v', 1);
$header .= pack('v', 1);
$header .= pack('V', $sampleRate);
$header .= pack('V', $sampleRate);
$header .= pack('v', 1);
$header .= pack('v', 8);
$header .= 'data';
$header .= pack('V', $dataSize);

header('Content-Type: audio/wav');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Content-Length: ' . (44 + $dataSize));
echo $header . $pcm;
exit;

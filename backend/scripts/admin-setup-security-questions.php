<?php

declare(strict_types=1);

/**
 * Script local de configuracao das perguntas de seguranca do administrador.
 *
 * Configure no backend/.env:
 *   ADMIN_SECURITY_EMAIL=
 *   ADMIN_SECURITY_QUESTION_1=
 *   ADMIN_SECURITY_ANSWER_1= ou ADMIN_SECURITY_ANSWER_1_OBFUSCATED=
 *   ADMIN_SECURITY_QUESTION_2=
 *   ADMIN_SECURITY_ANSWER_2= ou ADMIN_SECURITY_ANSWER_2_OBFUSCATED=
 *   ADMIN_SECURITY_QUESTION_3=
 *   ADMIN_SECURITY_ANSWER_3= ou ADMIN_SECURITY_ANSWER_3_OBFUSCATED=
 */

require_once __DIR__ . '/../core/bootstrap.php';

$isCli = PHP_SAPI === 'cli';

$out = function (string $text) use ($isCli): void {
    echo $isCli ? $text . "\n" : nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')) . "<br>\n";
};

if (!$isCli) {
    echo "<!DOCTYPE html><html lang='pt-BR'><head><meta charset='UTF-8'>";
    echo "<title>Setup Perguntas de Seguranca</title>";
    echo "<style>body{font-family:monospace;background:#111;color:#ccc;padding:32px;max-width:800px;}";
    echo ".ok{color:#6ee7b7;} .err{color:#fca5a5;} .warn{color:#fcd34d;}</style></head><body>\n";
    echo "<h2 style='color:#fff'>Configuracao de Perguntas de Seguranca</h2>\n";
}

function admin_security_setup_questions_from_env(): array
{
    $questions = [];

    for ($index = 1; $index <= 3; $index++) {
        $questions[] = [
            'pergunta' => trim((string) env('ADMIN_SECURITY_QUESTION_' . $index, '')),
            'resposta' => (string) secret_value('ADMIN_SECURITY_ANSWER_' . $index, ''),
        ];
    }

    return $questions;
}

$emailDoAdmin = normalize_email((string) env('ADMIN_SECURITY_EMAIL', ''));
$perguntas = admin_security_setup_questions_from_env();
$erros = [];

if (!filter_var($emailDoAdmin, FILTER_VALIDATE_EMAIL)) {
    $erros[] = 'Defina ADMIN_SECURITY_EMAIL no backend/.env.';
}

foreach ($perguntas as $i => $p) {
    if (trim($p['pergunta']) === '') {
        $erros[] = 'Defina ADMIN_SECURITY_QUESTION_' . ($i + 1) . ' no backend/.env.';
    }

    if (trim($p['resposta']) === '') {
        $erros[] = 'Defina ADMIN_SECURITY_ANSWER_' . ($i + 1)
            . ' ou ADMIN_SECURITY_ANSWER_' . ($i + 1) . '_OBFUSCATED no backend/.env.';
    }
}

if ($erros !== []) {
    foreach ($erros as $erro) {
        $out('ERRO: ' . $erro);
    }

    if (!$isCli) {
        echo '</body></html>';
    }

    exit(1);
}

$stmt = db()->prepare('SELECT id, name, email FROM admins WHERE email = :email LIMIT 1');
$stmt->execute(['email' => $emailDoAdmin]);
$admin = $stmt->fetch();

if (!$admin) {
    $out("ERRO: Nenhum admin encontrado com o e-mail '{$emailDoAdmin}'.");

    if (!$isCli) {
        echo '</body></html>';
    }

    exit(1);
}

$adminId = (int) $admin['id'];
$out("Admin encontrado: {$admin['name']} (id={$adminId})");
$out('');

$del = db()->prepare('DELETE FROM admin_security_questions WHERE admin_id = :id');
$del->execute(['id' => $adminId]);
$out('Perguntas anteriores removidas (se existiam).');

$insert = db()->prepare(
    'INSERT INTO admin_security_questions (admin_id, question_order, question, answer_hash)
     VALUES (:admin_id, :order, :question, :hash)'
);

foreach ($perguntas as $i => $p) {
    $normalizado = mb_strtolower(trim($p['resposta']));
    $hash = password_hash($normalizado, PASSWORD_DEFAULT);

    $insert->execute([
        'admin_id' => $adminId,
        'order' => $i + 1,
        'question' => trim($p['pergunta']),
        'hash' => $hash,
    ]);

    $out('  [' . ($i + 1) . '] ' . trim($p['pergunta']));
    $out('       Resposta salva com hash seguro.');
}

$out('');
$out('Perguntas de seguranca configuradas com sucesso.');
$out('Recomenda-se remover ADMIN_SECURITY_ANSWER_* do ambiente local depois de concluir o setup.');

if (!$isCli) {
    echo "<br><p style='color:#fcd34d;font-weight:bold'>Remova as respostas do .env apos o uso.</p>";
    echo '</body></html>';
}

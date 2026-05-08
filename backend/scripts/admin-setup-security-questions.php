<?php
declare(strict_types=1);
/**
 * Script de configuração das perguntas de segurança do administrador.
 *
 * COMO USAR:
 *   1. Edite as perguntas e respostas abaixo (seção CONFIGURAÇÃO)
 *   2. Acesse via navegador: http://localhost:8000/backend/scripts/admin-setup-security-questions.php
 *      OU rode via terminal: php backend/scripts/admin-setup-security-questions.php
 *   3. Após configurar, delete ou mova este arquivo (ele não deve ficar acessível em produção)
 */

// ─── CONFIGURAÇÃO — edite aqui ────────────────────────────────────────────────

$EMAIL_DO_ADMIN = 'bea.coorp@gmail.com'; // e-mail cadastrado na tabela admins

$PERGUNTAS = [
    [
        'pergunta' => 'Qual é o nome da cidade onde você nasceu?',
        'resposta' => 'Curitiba',
    ],
    [
        'pergunta' => 'Qual era o nome do seu primeiro animal de estimação?',
        'resposta' => 'Sushi',
    ],
    [
        'pergunta' => 'Qual é o nome da sua escola primária?',
        'resposta' => 'Objetivo',
    ],
];

// ─────────────────────────────────────────────────────────────────────────────



require_once __DIR__ . '/../core/bootstrap.php';

$isCli = PHP_SAPI === 'cli';

$out = function (string $text) use ($isCli) {
    echo $isCli ? $text . "\n" : nl2br(htmlspecialchars($text)) . "<br>\n";
};

if (!$isCli) {
    echo "<!DOCTYPE html><html lang='pt-BR'><head><meta charset='UTF-8'>";
    echo "<title>Setup Perguntas de Segurança</title>";
    echo "<style>body{font-family:monospace;background:#111;color:#ccc;padding:32px;max-width:800px;}";
    echo ".ok{color:#6ee7b7;} .err{color:#fca5a5;} .warn{color:#fcd34d;}</style></head><body>\n";
    echo "<h2 style='color:#fff'>Configuração de Perguntas de Segurança</h2>\n";
}

// Validações básicas
$erros = [];

if ($EMAIL_DO_ADMIN === 'seu-email@exemplo.com') {
    $erros[] = 'Defina o EMAIL_DO_ADMIN no topo do script.';
}

foreach ($PERGUNTAS as $i => $p) {
    if (trim($p['pergunta']) === '') {
        $erros[] = "Pergunta " . ($i + 1) . " está vazia.";
    }
    if (trim($p['resposta']) === '' || $p['resposta'] === 'sua resposta aqui') {
        $erros[] = "Resposta " . ($i + 1) . " não foi preenchida.";
    }
}

if (!empty($erros)) {
    foreach ($erros as $erro) {
        $out("ERRO: $erro");
    }
    if (!$isCli) {
        echo "</body></html>";
    }
    exit(1);
}

// Busca o admin pelo e-mail
$stmt = db()->prepare('SELECT id, name, email FROM admins WHERE email = :email LIMIT 1');
$stmt->execute(['email' => trim($EMAIL_DO_ADMIN)]);
$admin = $stmt->fetch();

if (!$admin) {
    $out("ERRO: Nenhum admin encontrado com o e-mail '{$EMAIL_DO_ADMIN}'.");
    if (!$isCli) echo "</body></html>";
    exit(1);
}

$adminId = (int) $admin['id'];
$out("Admin encontrado: {$admin['name']} (id={$adminId})");
$out('');

// Remove perguntas anteriores deste admin (permite reconfigurar)
$del = db()->prepare('DELETE FROM admin_security_questions WHERE admin_id = :id');
$del->execute(['id' => $adminId]);
$out("Perguntas anteriores removidas (se existiam).");

// Insere as novas perguntas
$insert = db()->prepare(
    'INSERT INTO admin_security_questions (admin_id, question_order, question, answer_hash)
     VALUES (:admin_id, :order, :question, :hash)'
);

foreach ($PERGUNTAS as $i => $p) {
    $normalizado = mb_strtolower(trim($p['resposta']));
    $hash        = password_hash($normalizado, PASSWORD_DEFAULT);

    $insert->execute([
        'admin_id' => $adminId,
        'order'    => $i + 1,
        'question' => trim($p['pergunta']),
        'hash'     => $hash,
    ]);

    $out("  [" . ($i + 1) . "] " . trim($p['pergunta']));
    $out("       Resposta salva (normalizada: \"{$normalizado}\")");
}

$out('');
$out('Perguntas de segurança configuradas com sucesso!');
$out('IMPORTANTE: delete ou mova este arquivo agora que a configuração foi feita.');

if (!$isCli) {
    echo "<br><p style='color:#fcd34d;font-weight:bold'>⚠ Delete este arquivo após o uso.</p>";
    echo "</body></html>";
}

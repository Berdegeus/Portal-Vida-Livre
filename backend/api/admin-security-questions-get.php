<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();

$pending = require_admin_security_questions_pending();
$adminId = (int) $pending['admin_id'];

if (is_admin_security_questions_locked($adminId)) {
    $horas = (int) ceil(get_admin_security_question_lockout_seconds($adminId) / 3600);
    error_response(
        "Acesso bloqueado por tentativas incorretas. Tente novamente em {$horas} hora(s).",
        ['locked' => true, 'hours' => $horas],
        429
    );
}

$questions = get_admin_security_questions($adminId);

if (count($questions) < 3) {
    error_response('Perguntas de seguranca nao configuradas para este administrador. Contate o suporte.', [], 500);
}

success_response('Perguntas carregadas.', [
    'questions'  => array_map(
        fn($q) => ['order' => (int) $q['question_order'], 'text' => $q['question']],
        $questions
    ),
    'csrf_token' => rotate_csrf_token(),
]);

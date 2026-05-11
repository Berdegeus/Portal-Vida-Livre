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
        "Acesso bloqueado. Tente novamente em {$horas} hora(s).",
        ['locked' => true, 'hours' => $horas],
        429
    );
}

$data    = request_data();
$answers = $data['answers'] ?? [];

if (!is_array($answers) || count($answers) !== 3) {
    error_response('Responda todas as tres perguntas.', [], 422);
}

$answers = array_values(array_map(
    fn($a) => mb_strtolower(trim((string) $a)),
    $answers
));

foreach ($answers as $answer) {
    if ($answer === '') {
        error_response('Nenhuma resposta pode ficar em branco.', [], 422);
    }
}

if (!verify_admin_security_answers($adminId, $answers)) {
    $attempts = increment_admin_security_question_attempts($adminId);

    if ($attempts >= 2) {
        $horas = (int) ceil(get_admin_security_question_lockout_seconds($adminId) / 3600);
        log_audit('admin.security_questions_locked', [
            'actor_type' => 'admin',
            'actor_id'   => $adminId,
        ]);
        error_response(
            "Respostas incorretas. Acesso bloqueado por {$horas} hora(s).",
            ['locked' => true, 'hours' => $horas],
            429
        );
    }

    $restantes = 2 - $attempts;
    error_response(
        "Respostas incorretas. Voce ainda tem {$restantes} tentativa(s).",
        ['remaining_attempts' => $restantes],
        401
    );
}

clear_admin_security_questions_pending();
clear_admin_security_question_lockout($adminId);

$admin = find_admin_by_id($adminId);

if ($admin === null) {
    error_response('Administrador nao encontrado.', [], 500);
}

$publicAdmin = login_admin($admin);

log_audit('admin.login_via_security_questions', [
    'actor_type'  => 'admin',
    'actor_id'    => $admin['id'],
    'actor_email' => $admin['email'],
]);

success_response('Acesso autorizado.', [
    'admin'      => $publicAdmin,
    'csrf_token' => rotate_csrf_token(),
]);

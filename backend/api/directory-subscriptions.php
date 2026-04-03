<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

$user = current_user();

if ($user === null) {
    error_response('Sessao invalida.', [], 401);
}

if (request_method() === 'GET') {
    success_response('Inscricoes carregadas com sucesso.', [
        'subscriptions' => list_user_directory_subscriptions((int) $user['id']),
        'subscription_ids' => list_user_directory_subscription_ids((int) $user['id']),
    ]);
}

if (request_method() !== 'POST') {
    error_response('Metodo nao permitido.', [], 405);
}

require_csrf();

$data = request_data();
$entryId = (int) ($data['entry_id'] ?? 0);
$action = trim((string) ($data['action'] ?? 'subscribe'));
$errors = [];

if ($entryId <= 0) {
    add_error($errors, 'entry_id', 'Item informado invalido.');
}

if (!in_array($action, ['subscribe', 'unsubscribe'], true)) {
    add_error($errors, 'action', 'Acao informada invalida.');
}

if (has_errors($errors)) {
    error_response('Verifique os campos informados.', $errors, 422);
}

$entry = find_directory_entry_by_id($entryId);

if ($entry === null) {
    error_response('Item nao encontrado.', [], 404);
}

if ($action === 'unsubscribe') {
    unsubscribe_user_from_directory_entry((int) $user['id'], $entryId);

    success_response('Inscricao removida com sucesso.', [
        'subscribed' => false,
        'entry' => directory_entry_public_data($entry),
    ]);
}

subscribe_user_to_directory_entry((int) $user['id'], $entryId);

success_response('Inscricao realizada com sucesso.', [
    'subscribed' => true,
    'entry' => directory_entry_public_data($entry),
]);

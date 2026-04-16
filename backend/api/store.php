<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (request_method() !== 'POST') {
    error_response('Método não permitido.', [], 405);
}

require_csrf();

// ── Lê e extrai os campos ─────────────────────────────────────────────────────
$body = request_data();

$entryType   = trim((string) ($body['entry_type']   ?? ''));
$name        = sanitize_name((string) ($body['name'] ?? ''));
$specialty   = trim(strip_tags((string) ($body['specialty'] ?? '')));
$slug        = normalize_slug((string) ($body['slug'] ?? ''));
$city        = trim(strip_tags((string) ($body['city'] ?? '')));
$state       = strtoupper(trim((string) ($body['state'] ?? '')));
$serviceMode = trim((string) ($body['service_mode'] ?? ''));
$shortBio    = trim(strip_tags((string) ($body['short_bio'] ?? '')));

// ── Validação ─────────────────────────────────────────────────────────────────
$errors = [];

$validTypes = ['professional', 'clinic', 'support_group'];
if (!in_array($entryType, $validTypes, true)) {
    add_error($errors, 'entry_type', 'Tipo de cadastro inválido.');
}

if (mb_strlen($name) < 2 || mb_strlen($name) > 160) {
    add_error($errors, 'name', 'Nome deve ter entre 2 e 160 caracteres.');
} elseif (!preg_match('/^[a-zA-ZÀ-ÿ\s\.\']+$/u', $name)) {
    add_error($errors, 'name', 'O nome deve conter apenas letras, espaços, pontos ou apóstrofos.');
}

if (mb_strlen($specialty) < 2 || mb_strlen($specialty) > 160) {
    add_error($errors, 'specialty', 'Especialidade deve ter entre 2 e 160 caracteres.');
} elseif (!preg_match('/^[a-zA-ZÀ-ÿ\s\.\,\-]+$/u', $specialty)) {
    add_error($errors, 'specialty', 'A especialidade contém caracteres inválidos.');
}

if (mb_strlen($slug) < 3 || mb_strlen($slug) > 160 || !preg_match('/^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])$/', $slug)) {
    add_error($errors, 'slug', 'Endereço do perfil inválido. Use apenas letras minúsculas, números e hífens (mínimo 3 caracteres).');
}

if (mb_strlen($city) < 2 || mb_strlen($city) > 120) {
    add_error($errors, 'city', 'Cidade deve ter entre 2 e 120 caracteres.');
} elseif (!preg_match('/^[a-zA-ZÀ-ÿ\s\'\-]+$/u', $city)) {
    add_error($errors, 'city', 'A cidade deve conter apenas letras, espaços, hífens ou apóstrofos.');
}

$validStates = [
    'AC','AL','AP','AM','BA','CE','DF','ES','GO','MA',
    'MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN',
    'RS','RO','RR','SC','SP','SE','TO',
];
if (!in_array($state, $validStates, true)) {
    add_error($errors, 'state', 'Estado inválido. Informe a sigla UF (ex.: SP).');
}

$validModes = ['online', 'presencial', 'hibrido'];
if (!in_array($serviceMode, $validModes, true)) {
    add_error($errors, 'service_mode', 'Modo de atendimento inválido.');
}

$bioLen = mb_strlen($shortBio);
if ($bioLen < 80 || $bioLen > 1200) {
    add_error($errors, 'short_bio', "Descrição deve ter entre 80 e 1200 caracteres (atual: {$bioLen}).");
}

if (has_errors($errors)) {
    error_response('Verifique os campos informados.', $errors, 422);
}

// ── Verifica se o slug já está em uso ─────────────────────────────────────────
$stmtCheck = db()->prepare('SELECT id FROM directory_entries WHERE slug = :slug LIMIT 1');
$stmtCheck->execute([':slug' => $slug]);

if ($stmtCheck->fetch()) {
    error_response('Verifique os campos informados.', [
        'slug' => ['Este endereço de perfil já está em uso. Escolha outro.'],
    ], 422);
}

// ── Insere no banco ───────────────────────────────────────────────────────────
try {
    $stmt = db()->prepare('
        INSERT INTO directory_entries
            (slug, entry_type, name, specialty, city, state, service_mode, short_bio)
        VALUES
            (:slug, :entry_type, :name, :specialty, :city, :state, :service_mode, :short_bio)
    ');
    $stmt->execute([
        ':slug'         => $slug,
        ':entry_type'   => $entryType,
        ':name'         => $name,
        ':specialty'    => $specialty,
        ':city'         => $city,
        ':state'        => $state,
        ':service_mode' => $serviceMode,
        ':short_bio'    => $shortBio,
    ]);

    $newId = (int) db()->lastInsertId();

} catch (\PDOException $e) {
    if ($e->getCode() === '23000') {
        error_response('Verifique os campos informados.', [
            'slug' => ['Este endereço de perfil já está em uso. Escolha outro.'],
        ], 422);
    }
    error_response('Não foi possível salvar o cadastro agora.', [], 500);
}

success_response(
    'Cadastro enviado com sucesso! Nossa equipe revisará em até 2 dias úteis.',
    ['id' => $newId, 'slug' => $slug],
    201
);
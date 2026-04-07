<?php

declare(strict_types=1);
require_once __DIR__ . '/../core/bootstrap.php';

// ── CORS / Headers ────────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$allowedOrigin = $_ENV['APP_URL'] ?? '*';
header("Access-Control-Allow-Origin: {$allowedOrigin}");
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método não permitido.', 405);
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function jsonError(string $message, int $status = 422): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonSuccess(string $message, array $data = []): never
{
    http_response_code(201);
    $payload = ['success' => true, 'message' => $message];
    if ($data) $payload['data'] = $data;
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitize(mixed $value): string
{
    return trim((string) ($value ?? ''));
}

function normalizeSlug(mixed $value): string
{
    $slug = sanitize($value);

    if ($slug === '') {
        return '';
    }

    if (function_exists('iconv')) {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);

        if (is_string($ascii) && $ascii !== '') {
            $slug = $ascii;
        }
    }

    $slug = strtolower($slug);
    $slug = preg_replace('/[\s_]+/', '-', $slug) ?? '';
    $slug = preg_replace('/-+/', '-', $slug) ?? '';

    return trim($slug, '-');
}

// ── Parse body ────────────────────────────────────────────────────────────────
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    jsonError('Corpo da requisição inválido (JSON malformado).', 400);
}

// ── Extract & sanitize fields ─────────────────────────────────────────────────
$entryType   = sanitize($body['entry_type']   ?? '');
$name        = sanitize($body['name']         ?? '');
$specialty   = sanitize($body['specialty']    ?? '');
$slug        = normalizeSlug($body['slug']    ?? '');
$city        = sanitize($body['city']         ?? '');
$state       = strtoupper(sanitize($body['state'] ?? ''));
$serviceMode = sanitize($body['service_mode'] ?? '');
$shortBio    = sanitize($body['short_bio']    ?? '');

// ── Validation ────────────────────────────────────────────────────────────────
$validTypes = ['professional', 'clinic', 'support_group'];
if (!in_array($entryType, $validTypes, true)) {
    jsonError('Tipo de cadastro inválido. Use: professional, clinic ou support_group.');
}

if (mb_strlen($name) < 2 || mb_strlen($name) > 160) {
    jsonError('Nome deve ter entre 2 e 160 caracteres.');
}
if (!preg_match('/^[a-zA-ZÀ-ÿ\s\.\']+$/u', $name)) {
    jsonError('O nome deve conter apenas letras, espaços, pontos ou apóstrofos.');
}

if (mb_strlen($specialty) < 2 || mb_strlen($specialty) > 160) {
    jsonError('Especialidade deve ter entre 2 e 160 caracteres.');
}
if (!preg_match('/^[a-zA-ZÀ-ÿ\s\.\,\-]+$/u', $specialty)) {
    jsonError('A especialidade contém caracteres inválidos.');
}

if (mb_strlen($slug) < 3 || mb_strlen($slug) > 160 || !preg_match('/^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])$/', $slug)) {
    jsonError('Endereço do perfil inválido. Use apenas letras minúsculas, números e hífens (mínimo 3 caracteres).');
}

if (mb_strlen($city) < 2 || mb_strlen($city) > 120) {
    jsonError('Cidade deve ter entre 2 e 120 caracteres.');
}
if (!preg_match('/^[a-zA-ZÀ-ÿ\s\'\-]+$/u', $city)) {
    jsonError('A cidade deve conter apenas letras, espaços, hífens ou apóstrofos.');
}

$validStates = [
    'AC','AL','AP','AM','BA','CE','DF','ES','GO','MA',
    'MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN',
    'RS','RO','RR','SC','SP','SE','TO',
];
if (!in_array($state, $validStates, true)) {
    jsonError('Estado inválido. Informe a sigla UF (ex.: SP).');
}

$validModes = ['online', 'presencial', 'hibrido'];
if (!in_array($serviceMode, $validModes, true)) {
    jsonError('Modo de atendimento inválido. Use: online, presencial ou hibrido.');
}

$bioLen = mb_strlen($shortBio);
if ($bioLen < 80 || $bioLen > 1200) {
    jsonError("Descrição deve ter entre 80 e 1200 caracteres (atual: {$bioLen}).");
}
if (preg_match('/[<>]/', $shortBio)) {
    jsonError('A descrição não pode conter tags HTML (< ou >) por questões de segurança.');
}

// ── Database ──────────────────────────────────────────────────────────────────

try {
    $pdo = db();
} catch (Throwable $e) {
    jsonError('Erro do Banco: ' . $e->getMessage(), 500);
}

// ── Check slug uniqueness ─────────────────────────────────────────────────────
try {
    $stmtCheck = $pdo->prepare(
        'SELECT id FROM directory_entries WHERE slug = :slug LIMIT 1'
    );
    $stmtCheck->execute([':slug' => $slug]);

    if ($stmtCheck->fetch()) {
        jsonError('Este endereço de perfil já está em uso. Escolha outro.');
    }
} catch (PDOException $e) {
    jsonError('Erro ao verificar disponibilidade do endereço. ' . $e->getMessage(), 500);
}

// ── Insert ────────────────────────────────────────────────────────────────────
$sql = <<<SQL
    INSERT INTO directory_entries
        (slug, entry_type, name, specialty, city, state, service_mode, short_bio)
    VALUES
        (:slug, :entry_type, :name, :specialty, :city, :state, :service_mode, :short_bio)
SQL;

try {
    $stmt = $pdo->prepare($sql);
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

    $newId = (int) $pdo->lastInsertId();

} catch (PDOException $e) {
    // Unique constraint violation (slug race condition fallback)
    if ($e->getCode() === '23000') {
        jsonError('Este endereço de perfil já está em uso. Escolha outro.');
    }

    jsonError('Erro ao salvar cadastro. ' . $e->getMessage(), 500);
}

// ── Success ───────────────────────────────────────────────────────────────────
jsonSuccess(
    'Cadastro enviado com sucesso! Nossa equipe revisará em até 2 dias úteis.',
    ['id' => $newId, 'slug' => $slug]
);
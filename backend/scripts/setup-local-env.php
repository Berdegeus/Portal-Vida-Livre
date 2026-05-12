<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Use este script apenas pela CLI.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/secrets.php';

function setup_option(string $name): ?string
{
    foreach ($_SERVER['argv'] ?? [] as $argument) {
        if ($argument === '--' . $name) {
            return '1';
        }

        $prefix = '--' . $name . '=';

        if (str_starts_with($argument, $prefix)) {
            return substr($argument, strlen($prefix));
        }
    }

    return null;
}

function setup_has_flag(string $name): bool
{
    return setup_option($name) !== null;
}

function setup_print_help(): void
{
    echo "Uso:\n";
    echo "  php backend/scripts/setup-local-env.php\n";
    echo "  php backend/scripts/setup-local-env.php --force\n";
    echo "  php backend/scripts/setup-local-env.php --output=backend/.env.teste --force\n\n";
    echo "O script:\n";
    echo "  - reaproveita valores ja presentes no .env de destino;\n";
    echo "  - pergunta apenas o que estiver faltando;\n";
    echo "  - cobre aplicacao, banco, SMTP, Telegram e perguntas de seguranca;\n";
    echo "  - grava segredos somente em *_OBFUSCATED.\n";
}

function setup_parse_env_file(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return [];
    }

    $values = [];

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (
            strlen($value) >= 2 &&
            (($value[0] === '"' && $value[strlen($value) - 1] === '"') ||
            ($value[0] === "'" && $value[strlen($value) - 1] === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $values[$name] = $value;
    }

    return $values;
}

function setup_existing_value(array $env, string $name): ?string
{
    if (!array_key_exists($name, $env)) {
        return null;
    }

    $value = trim((string) $env[$name]);

    return $value === '' ? null : $value;
}

function setup_existing_secret(array $env, string $name): ?string
{
    $direct = setup_existing_value($env, $name);

    if ($direct !== null) {
        return $direct;
    }

    $obfuscated = setup_existing_value($env, $name . '_OBFUSCATED');

    if ($obfuscated === null) {
        return null;
    }

    return resolve_obfuscated_secret($obfuscated);
}

function setup_prompt(string $label, ?string $default = null, bool $allowEmpty = false): string
{
    while (true) {
        $suffix = $default !== null ? ' [' . $default . ']' : '';
        fwrite(STDOUT, $label . $suffix . ': ');
        $line = fgets(STDIN);

        if ($line === false) {
            return $default ?? '';
        }

        $value = preg_replace('/^\xEF\xBB\xBF/', '', trim($line)) ?? trim($line);

        if ($value === '' && $default !== null) {
            return $default;
        }

        if ($value === '' && !$allowEmpty) {
            fwrite(STDOUT, "Informe um valor ou use o padrao sugerido.\n");
            continue;
        }

        return $value;
    }
}

function setup_prompt_missing(
    array $env,
    string $name,
    string $label,
    ?string $default = null,
    bool $allowEmpty = false
): string {
    $existing = setup_existing_value($env, $name);

    return $existing ?? setup_prompt($label, $default, $allowEmpty);
}

function setup_prompt_missing_secret(
    array $env,
    string $name,
    string $label,
    ?string $default = null,
    bool $allowEmpty = false
): string {
    $existing = setup_existing_secret($env, $name);

    return $existing ?? setup_prompt($label, $default, $allowEmpty);
}

function setup_confirm(string $label, bool $default = false): bool
{
    $hint = $default ? ' [S/n]' : ' [s/N]';
    fwrite(STDOUT, $label . $hint . ': ');
    $line = fgets(STDIN);
    $value = strtolower(trim((string) $line));

    if ($value === '') {
        return $default;
    }

    return in_array($value, ['s', 'sim', 'y', 'yes'], true);
}

function setup_generate_password(): string
{
    return rtrim(strtr(base64_encode(random_bytes(30)), '+/', '-_'), '=') . 'aA1!';
}

function setup_generate_app_key(): string
{
    return 'base64:' . base64_encode(random_bytes(32));
}

function setup_has_group_values(array $env, array $plainNames, array $secretNames = []): bool
{
    foreach ($plainNames as $name) {
        if (setup_existing_value($env, $name) !== null) {
            return true;
        }
    }

    foreach ($secretNames as $name) {
        if (setup_existing_secret($env, $name) !== null) {
            return true;
        }
    }

    return false;
}

function setup_optional_obfuscated(?string $value): string
{
    return $value === null ? '' : obfuscate_secret($value);
}

function setup_collect_core_values(array $env): array
{
    return [
        'app_host' => setup_prompt_missing($env, 'APP_HOST', 'Host da aplicacao', 'localhost'),
        'app_port' => setup_prompt_missing($env, 'APP_PORT', 'Porta da aplicacao', '8000'),
        'app_key' => setup_existing_secret($env, 'APP_KEY') ?? setup_generate_app_key(),
        'db_host' => setup_prompt_missing($env, 'DB_HOST', 'Host do MySQL/MariaDB', '127.0.0.1'),
        'db_port' => setup_prompt_missing($env, 'DB_PORT', 'Porta do MySQL/MariaDB', '3306'),
        'db_database' => setup_prompt_missing($env, 'DB_DATABASE', 'Nome do banco', 'portal_vida_livre'),
        'db_username' => setup_existing_value($env, 'DB_USERNAME') ?? 'portal_app_runtime',
        'db_password' => setup_existing_secret($env, 'DB_PASSWORD') ?? setup_generate_password(),
        'db_public_username' => setup_existing_value($env, 'DB_PUBLIC_USERNAME') ?? 'portal_public_reader',
        'db_public_password' => setup_existing_secret($env, 'DB_PUBLIC_PASSWORD') ?? setup_generate_password(),
        'db_admin_username' => setup_existing_value($env, 'DB_ADMIN_USERNAME') ?? 'portal_admin_runtime',
        'db_admin_password' => setup_existing_secret($env, 'DB_ADMIN_PASSWORD') ?? setup_generate_password(),
        'db_report_username' => setup_existing_value($env, 'DB_REPORT_USERNAME') ?? 'portal_report_reader',
        'db_report_password' => setup_existing_secret($env, 'DB_REPORT_PASSWORD') ?? setup_generate_password(),
        'db_maintenance_username' => setup_prompt_missing(
            $env,
            'DB_MAINTENANCE_USERNAME',
            'Usuario de manutencao do banco',
            'root'
        ),
        'db_maintenance_password' => setup_prompt_missing_secret(
            $env,
            'DB_MAINTENANCE_PASSWORD',
            'Senha do usuario de manutencao (pode ficar vazia)',
            null,
            true
        ),
        'db_charset' => setup_existing_value($env, 'DB_CHARSET') ?? 'utf8mb4',
        'db_collation' => setup_existing_value($env, 'DB_COLLATION') ?? 'utf8mb4_unicode_ci',
    ];
}

function setup_collect_smtp_values(array $env): array
{
    $configure = setup_has_group_values(
        $env,
        ['SMTP_HOST', 'SMTP_USERNAME', 'SMTP_FROM_EMAIL'],
        ['SMTP_PASSWORD']
    ) || setup_confirm('Configurar SMTP agora?', false);

    if (!$configure) {
        return [
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
            'smtp_username' => '',
            'smtp_password' => null,
            'smtp_from_email' => '',
            'smtp_from_name' => 'Portal Vida Livre',
        ];
    }

    $username = setup_prompt_missing($env, 'SMTP_USERNAME', 'Usuario SMTP');

    return [
        'smtp_host' => setup_prompt_missing($env, 'SMTP_HOST', 'Host SMTP'),
        'smtp_port' => setup_prompt_missing($env, 'SMTP_PORT', 'Porta SMTP', '587'),
        'smtp_encryption' => setup_prompt_missing($env, 'SMTP_ENCRYPTION', 'Criptografia SMTP', 'tls'),
        'smtp_username' => $username,
        'smtp_password' => setup_prompt_missing_secret($env, 'SMTP_PASSWORD', 'Senha SMTP'),
        'smtp_from_email' => setup_prompt_missing(
            $env,
            'SMTP_FROM_EMAIL',
            'E-mail remetente SMTP',
            $username
        ),
        'smtp_from_name' => setup_prompt_missing(
            $env,
            'SMTP_FROM_NAME',
            'Nome remetente SMTP',
            'Portal Vida Livre'
        ),
    ];
}

function setup_collect_telegram_values(array $env): array
{
    $configure = setup_has_group_values(
        $env,
        ['TELEGRAM_BOT_USERNAME'],
        ['TELEGRAM_BOT_TOKEN']
    ) || setup_confirm('Configurar Telegram agora?', false);

    if (!$configure) {
        return [
            'telegram_bot_token' => null,
            'telegram_bot_username' => '',
        ];
    }

    return [
        'telegram_bot_token' => setup_prompt_missing_secret($env, 'TELEGRAM_BOT_TOKEN', 'Token do bot Telegram'),
        'telegram_bot_username' => setup_prompt_missing($env, 'TELEGRAM_BOT_USERNAME', 'Username do bot Telegram'),
    ];
}

function setup_collect_admin_security_values(array $env): array
{
    $configure = setup_has_group_values(
        $env,
        [
            'ADMIN_SECURITY_EMAIL',
            'ADMIN_SECURITY_QUESTION_1',
            'ADMIN_SECURITY_QUESTION_2',
            'ADMIN_SECURITY_QUESTION_3',
        ],
        [
            'ADMIN_SECURITY_ANSWER_1',
            'ADMIN_SECURITY_ANSWER_2',
            'ADMIN_SECURITY_ANSWER_3',
        ]
    ) || setup_confirm('Configurar perguntas de seguranca do admin agora?', false);

    if (!$configure) {
        return [
            'admin_security_email' => '',
            'admin_security_question_1' => '',
            'admin_security_answer_1' => null,
            'admin_security_question_2' => '',
            'admin_security_answer_2' => null,
            'admin_security_question_3' => '',
            'admin_security_answer_3' => null,
        ];
    }

    return [
        'admin_security_email' => setup_prompt_missing($env, 'ADMIN_SECURITY_EMAIL', 'E-mail do admin'),
        'admin_security_question_1' => setup_prompt_missing(
            $env,
            'ADMIN_SECURITY_QUESTION_1',
            'Pergunta de seguranca 1'
        ),
        'admin_security_answer_1' => setup_prompt_missing_secret(
            $env,
            'ADMIN_SECURITY_ANSWER_1',
            'Resposta da pergunta 1'
        ),
        'admin_security_question_2' => setup_prompt_missing(
            $env,
            'ADMIN_SECURITY_QUESTION_2',
            'Pergunta de seguranca 2'
        ),
        'admin_security_answer_2' => setup_prompt_missing_secret(
            $env,
            'ADMIN_SECURITY_ANSWER_2',
            'Resposta da pergunta 2'
        ),
        'admin_security_question_3' => setup_prompt_missing(
            $env,
            'ADMIN_SECURITY_QUESTION_3',
            'Pergunta de seguranca 3'
        ),
        'admin_security_answer_3' => setup_prompt_missing_secret(
            $env,
            'ADMIN_SECURITY_ANSWER_3',
            'Resposta da pergunta 3'
        ),
    ];
}

function setup_env_lines(array $values): array
{
    return [
        'APP_HOST=' . $values['app_host'],
        'APP_PORT=' . $values['app_port'],
        'APP_KEY_OBFUSCATED=' . obfuscate_secret($values['app_key']),
        '',
        'DB_HOST=' . $values['db_host'],
        'DB_PORT=' . $values['db_port'],
        'DB_DATABASE=' . $values['db_database'],
        'DB_USERNAME=' . $values['db_username'],
        'DB_PASSWORD_OBFUSCATED=' . obfuscate_secret($values['db_password']),
        'DB_PUBLIC_USERNAME=' . $values['db_public_username'],
        'DB_PUBLIC_PASSWORD_OBFUSCATED=' . obfuscate_secret($values['db_public_password']),
        'DB_ADMIN_USERNAME=' . $values['db_admin_username'],
        'DB_ADMIN_PASSWORD_OBFUSCATED=' . obfuscate_secret($values['db_admin_password']),
        'DB_REPORT_USERNAME=' . $values['db_report_username'],
        'DB_REPORT_PASSWORD_OBFUSCATED=' . obfuscate_secret($values['db_report_password']),
        'DB_MAINTENANCE_USERNAME=' . $values['db_maintenance_username'],
        'DB_MAINTENANCE_PASSWORD_OBFUSCATED=' . obfuscate_secret($values['db_maintenance_password']),
        'DB_CHARSET=' . $values['db_charset'],
        'DB_COLLATION=' . $values['db_collation'],
        '',
        'SMTP_HOST=' . $values['smtp_host'],
        'SMTP_PORT=' . $values['smtp_port'],
        'SMTP_ENCRYPTION=' . $values['smtp_encryption'],
        'SMTP_USERNAME=' . $values['smtp_username'],
        'SMTP_PASSWORD_OBFUSCATED=' . setup_optional_obfuscated($values['smtp_password']),
        'SMTP_FROM_EMAIL=' . $values['smtp_from_email'],
        'SMTP_FROM_NAME=' . $values['smtp_from_name'],
        '',
        'TELEGRAM_BOT_TOKEN_OBFUSCATED=' . setup_optional_obfuscated($values['telegram_bot_token']),
        'TELEGRAM_BOT_USERNAME=' . $values['telegram_bot_username'],
        '',
        'ADMIN_SECURITY_EMAIL=' . $values['admin_security_email'],
        'ADMIN_SECURITY_QUESTION_1=' . $values['admin_security_question_1'],
        'ADMIN_SECURITY_ANSWER_1_OBFUSCATED=' . setup_optional_obfuscated($values['admin_security_answer_1']),
        'ADMIN_SECURITY_QUESTION_2=' . $values['admin_security_question_2'],
        'ADMIN_SECURITY_ANSWER_2_OBFUSCATED=' . setup_optional_obfuscated($values['admin_security_answer_2']),
        'ADMIN_SECURITY_QUESTION_3=' . $values['admin_security_question_3'],
        'ADMIN_SECURITY_ANSWER_3_OBFUSCATED=' . setup_optional_obfuscated($values['admin_security_answer_3']),
    ];
}

if (setup_has_flag('help')) {
    setup_print_help();
    exit(0);
}

$backendDir = dirname(__DIR__);
$defaultEnvPath = $backendDir . '/.env';
$outputPath = setup_option('output') ?? $defaultEnvPath;
$force = setup_has_flag('force');
$existingEnv = setup_parse_env_file($outputPath);

if (file_exists($outputPath) && !$force) {
    if (!setup_confirm('O arquivo ' . $outputPath . ' ja existe. Atualizar usando os valores atuais?')) {
        fwrite(STDOUT, "Setup cancelado. Nenhum arquivo foi alterado.\n");
        exit(0);
    }
}

fwrite(STDOUT, "Configuracao local do Portal Vida Livre\n");
fwrite(STDOUT, "Valores ja existentes no .env serao reaproveitados sem nova pergunta.\n");
fwrite(STDOUT, "Segredos diretos, se existirem, serao regravados apenas em formato ofuscado.\n\n");

$values = array_merge(
    setup_collect_core_values($existingEnv),
    setup_collect_smtp_values($existingEnv),
    setup_collect_telegram_values($existingEnv),
    setup_collect_admin_security_values($existingEnv)
);

$directory = dirname($outputPath);

if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
    fwrite(STDERR, "Nao foi possivel criar o diretorio de destino.\n");
    exit(1);
}

$contents = implode(PHP_EOL, setup_env_lines($values)) . PHP_EOL;

if (file_put_contents($outputPath, $contents) === false) {
    fwrite(STDERR, "Nao foi possivel gravar o arquivo de ambiente.\n");
    exit(1);
}

fwrite(STDOUT, "\nArquivo gerado: {$outputPath}\n");
fwrite(STDOUT, "Proximos passos:\n");
fwrite(STDOUT, "  php backend/scripts/run-schema.php\n");
fwrite(STDOUT, "  php backend/scripts/apply-db-security.php\n");
fwrite(STDOUT, "  php serve.php\n");

<?php

declare(strict_types=1);

function server_db(): \PDO
{
    return maintenance_db(false);
}

function ensure_database_exists(): void
{
    $config = load_config('database');
    $database = str_replace('`', '``', (string) $config['database']);
    $charset = (string) $config['charset'];
    $collation = (string) ($config['collation'] ?? ($charset . '_unicode_ci'));

    $sql = sprintf(
        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s',
        $database,
        $charset,
        $collation
    );

    server_db()->exec($sql);
}

function schema_file_path(): string
{
    return dirname(__DIR__) . '/database/schema.sql';
}

function table_has_column(string $table, string $column): bool
{
    $statement = maintenance_db()->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :table
           AND column_name = :column'
    );
    $statement->execute([
        'table' => $table,
        'column' => $column,
    ]);

    return (int) $statement->fetchColumn() > 0;
}

function sync_schema_additions(): void
{
    if (!table_has_column('users', 'email_verified_at')) {
        maintenance_db()->exec(
            'ALTER TABLE users
             ADD COLUMN email_verified_at DATETIME NULL
             AFTER password_hash'
        );
    }
}

function run_add_column_if_not_exists(string $statement): bool
{
    $pattern = '/^ALTER\s+TABLE\s+`?([A-Za-z0-9_]+)`?\s+(.+);?$/is';

    if (!preg_match($pattern, trim($statement), $matches)) {
        return false;
    }

    $table = $matches[1];
    $clauses = preg_split(
        '/,\s*(?=ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS\s+`?[A-Za-z0-9_]+`?\s+)/i',
        rtrim(trim($matches[2]), ';')
    ) ?: [];

    if ($clauses === []) {
        return false;
    }

    foreach ($clauses as $clause) {
        if (!preg_match(
            '/^ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS\s+`?([A-Za-z0-9_]+)`?\s+(.+)$/is',
            trim($clause),
            $columnMatches
        )) {
            return false;
        }
    }

    foreach ($clauses as $clause) {
        preg_match(
            '/^ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS\s+`?([A-Za-z0-9_]+)`?\s+(.+)$/is',
            trim($clause),
            $columnMatches
        );

        $column = $columnMatches[1];
        $definition = rtrim(trim($columnMatches[2]), ';');

        if (table_has_column($table, $column)) {
            continue;
        }

        maintenance_db()->exec(sprintf(
            'ALTER TABLE `%s` ADD COLUMN `%s` %s',
            str_replace('`', '``', $table),
            str_replace('`', '``', $column),
            $definition
        ));
    }

    return true;
}

function split_sql_statements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $lines = preg_split('/\R/', $sql) ?: [];

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '--')) {
            continue;
        }

        $buffer .= $line . "\n";

        if (str_ends_with(rtrim($line), ';')) {
            $statement = trim($buffer);

            if ($statement !== '') {
                $statements[] = $statement;
            }

            $buffer = '';
        }
    }

    $buffer = trim($buffer);

    if ($buffer !== '') {
        $statements[] = $buffer;
    }

    return $statements;
}

function run_schema(): void
{
    ensure_database_exists();

    $sql = file_get_contents(schema_file_path());

    if ($sql === false) {
        throw new \RuntimeException('Nao foi possivel ler o schema.sql.');
    }

    foreach (split_sql_statements($sql) as $statement) {
        if (run_add_column_if_not_exists($statement)) {
            continue;
        }

        maintenance_db()->exec($statement);
    }

    sync_schema_additions();
}


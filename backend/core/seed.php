<?php

declare(strict_types=1);

function seed_file_path(): string
{
    return dirname(__DIR__) . '/database/seed.sql';
}

function run_seed(): void
{
    $sql = file_get_contents(seed_file_path());

    if ($sql === false) {
        throw new \RuntimeException('Nao foi possivel ler o seed.sql.');
    }

    foreach (split_sql_statements($sql) as $statement) {
        db()->exec($statement);
    }
}

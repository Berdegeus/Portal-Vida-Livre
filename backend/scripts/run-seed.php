<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once dirname(__DIR__) . '/core/env.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/schema.php';
require_once dirname(__DIR__) . '/core/seed.php';

load_env(dirname(__DIR__) . '/.env');
run_schema();
run_seed();

echo "Seed aplicado com sucesso.\n";

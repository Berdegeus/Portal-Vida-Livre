<?php

declare(strict_types=1);

function db_connection(string $name = 'runtime', bool $withDatabase = true): \PDO
{
    static $connections = [];

    $cacheKey = $name . ($withDatabase ? ':database' : ':server');

    if (isset($connections[$cacheKey]) && $connections[$cacheKey] instanceof \PDO) {
        return $connections[$cacheKey];
    }

    $config = load_config('database');
    [$username, $password] = database_connection_credentials($config, $name);

    $dsn = $withDatabase
        ? sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            (int) $config['port'],
            $config['database'],
            $config['charset']
        )
        : sprintf(
            'mysql:host=%s;port=%d;charset=%s',
            $config['host'],
            (int) $config['port'],
            $config['charset']
        );

    try {
        $connections[$cacheKey] = new \PDO(
            $dsn,
            $username,
            $password,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (\PDOException $exception) {
        throw new \RuntimeException(
            sprintf('Nao foi possivel conectar ao banco de dados (%s).', $name),
            0,
            $exception
        );
    }

    return $connections[$cacheKey];
}

function database_connection_credentials(array $config, string $name): array
{
    [$username, $password, $secretName] = match ($name) {
        'runtime' => [
            (string) $config['username'],
            (string) $config['password'],
            'DB_PASSWORD',
        ],
        'public' => [
            (string) $config['public_username'],
            (string) $config['public_password'],
            'DB_PUBLIC_PASSWORD',
        ],
        'admin' => [
            (string) $config['admin_username'],
            (string) $config['admin_password'],
            'DB_ADMIN_PASSWORD',
        ],
        'report' => [
            (string) $config['report_username'],
            (string) $config['report_password'],
            'DB_REPORT_PASSWORD',
        ],
        'maintenance' => [
            (string) $config['maintenance_username'],
            (string) $config['maintenance_password'],
            'DB_MAINTENANCE_PASSWORD',
        ],
        default => throw new \InvalidArgumentException('Conexao de banco desconhecida: ' . $name),
    };

    if ($name !== 'maintenance') {
        if ($password === '') {
            throw new \RuntimeException('Segredo obrigatorio ausente: ' . $secretName);
        }

        assert_secret_not_placeholder($secretName, $password);
    }

    return [$username, $password];
}

function db(): \PDO
{
    return db_connection('runtime');
}

function public_db(): \PDO
{
    return db_connection('public');
}

function admin_db(): \PDO
{
    return db_connection('admin');
}

function report_db(): \PDO
{
    return db_connection('report');
}

function maintenance_db(bool $withDatabase = true): \PDO
{
    return db_connection('maintenance', $withDatabase);
}


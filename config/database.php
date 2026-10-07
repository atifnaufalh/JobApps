<?php

use Illuminate\Support\Str;

return [
    'default' => env('DB_CONNECTION', 'sqlite'),
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL', env('DB_URL')),
            'host' => env('PGHOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('PGPORT', env('DB_PORT', '5432')),
            'database' => env('PGDATABASE', env('DB_DATABASE', 'jobagent')),
            'username' => env('PGUSER', env('DB_USERNAME', 'root')),
            'password' => env('PGPASSWORD', env('DB_PASSWORD', '')),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => env('DB_SCHEMA', 'public'),
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],
    ],
    'migrations' => ['table' => 'migrations', 'update_date_on_publish' => true],
    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => ['cluster' => env('REDIS_CLUSTER', 'redis'), 'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'jobagent')).'-database-')],
    ],
];

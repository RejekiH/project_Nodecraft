<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | FIX: Default diubah ke 'mongodb' karena semua Model module menggunakan
    | protected $connection = 'mongodb'. Sebelumnya 'mysql' yang menyebabkan
    | semua query gagal dengan "Base table or view not found".
    |--------------------------------------------------------------------------
    */
    'default' => env('DB_CONNECTION', 'mongodb'),

    'connections' => [

        'sqlite' => [
            'driver'                  => 'sqlite',
            'url'                     => env('DATABASE_URL'),
            'database'                => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix'                  => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        /*
        |----------------------------------------------------------------------
        | FIX: Koneksi MongoDB — WAJIB ada agar semua Model module berfungsi.
        |
        | Digunakan oleh:
        |   - App\Modules\UserService\Models\User
        |   - App\Modules\RoomService\Models\Room
        |   - App\Modules\GameplayService\Models\GameSession
        |   - App\Modules\GameplayService\Models\MoveRecord
        |   - App\Modules\BackupService\Models\BackupRecord
        |   - App\Modules\BackupService\Models\NodeStatus
        |
        | Membutuhkan package: mongodb/laravel-mongodb ^3.4
        | dan MongoDB\Laravel\MongoDBServiceProvider di config/app.php
        |----------------------------------------------------------------------
        */
        'mongodb' => [
            'driver'   => 'mongodb',
            'host'     => env('MONGODB_HOST', '127.0.0.1'),
            'port'     => (int) env('MONGODB_PORT', 27017),
            'database' => env('MONGODB_DATABASE', 'chess_app'),
            'username' => env('MONGODB_USERNAME', ''),
            'password' => env('MONGODB_PASSWORD', ''),
            'options'  => [
                'database' => env('MONGODB_DATABASE', 'chess_app'),
            ],
        ],

        'mysql' => [
            'driver'      => 'mysql',
            'url'         => env('DATABASE_URL'),
            'host'        => env('DB_HOST', '127.0.0.1'),
            'port'        => env('DB_PORT', '3306'),
            'database'    => env('DB_DATABASE', 'forge'),
            'username'    => env('DB_USERNAME', 'forge'),
            'password'    => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset'     => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'prefix'      => '',
            'prefix_indexes' => true,
            'strict'      => true,
            'engine'      => null,
            'options'     => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver'         => 'pgsql',
            'url'            => env('DATABASE_URL'),
            'host'           => env('DB_HOST', '127.0.0.1'),
            'port'           => env('DB_PORT', '5432'),
            'database'       => env('DB_DATABASE', 'forge'),
            'username'       => env('DB_USERNAME', 'forge'),
            'password'       => env('DB_PASSWORD', ''),
            'charset'        => 'utf8',
            'prefix'         => '',
            'prefix_indexes' => true,
            'search_path'    => 'public',
            'sslmode'        => 'prefer',
        ],

        'sqlsrv' => [
            'driver'         => 'sqlsrv',
            'url'            => env('DATABASE_URL'),
            'host'           => env('DB_HOST', 'localhost'),
            'port'           => env('DB_PORT', '1433'),
            'database'       => env('DB_DATABASE', 'forge'),
            'username'       => env('DB_USERNAME', 'forge'),
            'password'       => env('DB_PASSWORD', ''),
            'charset'        => 'utf8',
            'prefix'         => '',
            'prefix_indexes' => true,
        ],

    ],

    'migrations' => 'migrations',

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix'  => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url'      => env('REDIS_URL'),
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port'     => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url'      => env('REDIS_URL'),
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port'     => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];

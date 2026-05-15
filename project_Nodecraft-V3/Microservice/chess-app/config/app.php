<?php

use Illuminate\Support\Facades\Facade;

return [

    'name' => env('APP_NAME', 'Laravel'),
    'env'  => env('APP_ENV', 'production'),
    'debug'=> (bool) env('APP_DEBUG', false),
    'url'  => env('APP_URL', 'http://localhost'),
    'asset_url' => env('ASSET_URL'),
    'timezone' => 'UTC',
    'locale'   => 'en',
    'fallback_locale' => 'en',
    'faker_locale'    => 'en_US',
    'key'    => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Internal API Key
    |--------------------------------------------------------------------------
    |
    | FIX: Ditambahkan agar config('app.internal_api_key') berfungsi.
    | Dibaca oleh InternalApiKeyMiddleware di UserService dan RoomService.
    | Tanpa ini, semua request ke route /api/internal/* selalu ditolak (403)
    | karena hash_equals(null, $key) selalu false.
    |
    */
    'internal_api_key' => env('INTERNAL_API_KEY', ''),

    'maintenance' => ['driver' => 'file'],

    'providers' => [

        /*
         * Laravel Framework Service Providers
         */
        Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Hashing\HashServiceProvider::class,
        Illuminate\Mail\MailServiceProvider::class,
        Illuminate\Notifications\NotificationServiceProvider::class,
        Illuminate\Pagination\PaginationServiceProvider::class,
        Illuminate\Pipeline\PipelineServiceProvider::class,
        Illuminate\Queue\QueueServiceProvider::class,
        Illuminate\Redis\RedisServiceProvider::class,
        Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class,
        Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,

        /*
         * Package Service Providers
         *
         * FIX: MongoDB\Laravel\MongoDBServiceProvider ditambahkan agar
         * driver 'mongodb' di config/database.php dapat dikenali Laravel.
         * Tanpa ini semua query MongoDB throw "Driver [mongodb] not supported".
         */
        MongoDB\Laravel\MongoDBServiceProvider::class,

        /*
         * Application Service Providers
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,

        /*
         * CATATAN: Module Service Providers (UserService, RoomService,
         * GameplayService, BackupService) TIDAK didaftarkan di sini.
         * Mereka sudah terdaftar di bootstrap/app.php via withProviders().
         * Mendaftarkan ulang di sini akan menyebabkan double-boot dan
         * konflik singleton.
         */
    ],

    'aliases' => Facade::defaultAliases()->merge([
        //
    ])->toArray(),

];

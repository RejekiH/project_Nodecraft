<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chess Validator (Node.js)
    |--------------------------------------------------------------------------
    | GameplayService memanggil Node.js script untuk validasi move.
    | Pastikan node binary tersedia di PATH server.
    */
    'node_binary'            => env('NODE_BINARY', 'node'),
    'chess_validator_script' => env('CHESS_VALIDATOR_SCRIPT', base_path('scripts/chess_validator.js')),
    'validator_timeout'      => (int) env('CHESS_VALIDATOR_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    | Timeout (detik) untuk request ke RoomService.
    */
    'http_timeout'        => (int) env('GAMEPLAY_HTTP_TIMEOUT', 10),
    'room_service_url'    => env('ROOM_SERVICE_URL', env('APP_URL', 'http://localhost')),

    /*
    |--------------------------------------------------------------------------
    | Time Control defaults
    |--------------------------------------------------------------------------
    */
    'default_time_control' => env('DEFAULT_TIME_CONTROL', '10+0'),

];

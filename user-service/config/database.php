<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database Configuration - User Service
    |--------------------------------------------------------------------------
    |
    | User Service HANYA menggunakan MongoDB.
    | SQLite/MySQL tidak digunakan sama sekali.
    |
    */

    'default' => 'mongodb',

    'connections' => [

        /*
         * MongoDB Primary - untuk read/write
         */
        'mongodb' => [
            'driver'   => 'mongodb',
            'dsn'      => env('MONGODB_URI', 'mongodb://localhost:27017'),
            'database' => env('MONGODB_DATABASE', 'nodechess_users'),
            'username' => env('MONGODB_USERNAME', ''),
            'password' => env('MONGODB_PASSWORD', ''),
            'options'  => [
                'connectTimeoutMS' => 5000,
                'socketTimeoutMS'  => 10000,
            ],
        ],

        /*
         * MongoDB Replica - untuk read pada Fase 3
         * (Database & Schema)
         */
        'mongodb_replica' => [
            'driver'   => 'mongodb',
            'dsn'      => env('MONGODB_REPLICA_URI', 'mongodb://localhost:27018'),
            'database' => env('MONGODB_REPLICA_DATABASE', 'nodechess_users'),
            'options'  => [
                'readPreference' => 'secondaryPreferred',
            ],
        ],

    ],

    /*
     * Migration - tidak dipakai (MongoDB schema-less)
     * Index dibuat manual via MongoDB shell atau seeder
     */
    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

];

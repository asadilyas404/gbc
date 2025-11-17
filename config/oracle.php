<?php

return [
    'oracle' => [
        'driver'         => 'oracle',
        'tns'            => env('DB_TNS', ''),
        'host'           => env('DB_HOST', ''),
        'port'           => env('DB_PORT', '1521'),
        'database'       => env('DB_DATABASE', ''),
        'username'       => env('DB_USERNAME', ''),
        'password'       => env('DB_PASSWORD', ''),
        'charset'        => env('DB_CHARSET', 'AL32UTF8'),
        'prefix'         => env('DB_PREFIX', ''),
        'prefix_schema'  => env('DB_SCHEMA_PREFIX', ''),
        'edition'        => env('DB_EDITION', 'ora$base'),
        'server_version' => env('DB_SERVER_VERSION', '11g'),
    ],
    'live_oracle' => [
        'driver'         => env('ORACLE_LIVE_CONNECTION', ''),
        'tns'            => env('ORACLE_LIVE_TNS', ''),
        'host'           => env('ORACLE_LIVE_HOST', ''),
        'port'           => env('ORACLE_LIVE_PORT', '1521'),
        'database'       => env('ORACLE_LIVE_DATABASE', ''),
        'username'       => env('ORACLE_LIVE_USERNAME', ''),
        'password'       => env('ORACLE_LIVE_PASSWORD', ''),
        'charset'        => env('ORACLE_LIVE_CHARSET', 'AL32UTF8'),
        'prefix'         => env('ORACLE_LIVE_PREFIX', ''),
        'prefix_schema'  => env('ORACLE_LIVE_SCHEMA_PREFIX', ''),
        'edition'        => env('ORACLE_LIVE_EDITION', 'ora$base'),
        'server_version' => env('ORACLE_LIVE_SERVER_VERSION', '11g'),
    ],
];

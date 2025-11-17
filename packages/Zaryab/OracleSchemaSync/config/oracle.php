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
        'driver'         => env('DB_LIVE_CONNECTION', 'oracle'),
        'tns'            => env('DB_LIVE_TNS', ''),
        'host'           => env('DB_LIVE_HOST', ''),
        'port'           => env('DB_LIVE_PORT', '1521'),
        'database'       => env('DB_LIVE_DATABASE', ''),
        'username'       => env('DB_LIVE_USERNAME', ''),
        'password'       => env('DB_LIVE_PASSWORD', ''),
        'charset'        => env('DB_LIVE_CHARSET', 'AL32UTF8'),
        'prefix'         => env('DB_LIVE_PREFIX', ''),
        'prefix_schema'  => env('DB_LIVE_SCHEMA_PREFIX', ''),
        'edition'        => env('DB_LIVE_EDITION', 'ora$base'),
        'server_version' => env('DB_LIVE_SERVER_VERSION', '11g'),
    ],
];

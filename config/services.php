<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Live Server API Configuration (for syncing data)
    |--------------------------------------------------------------------------
    |
    */
    'live_server' => [
        'url' => env('LIVE_SERVER_API_URL', 'https://malikalpizza.royalerp.net/api/v1'),
        'token' => env('SYNC_API_TOKEN'),
        'timeout' => env('LIVE_SERVER_API_TIMEOUT', 60), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync API Configuration (for receiving sync data)
    |--------------------------------------------------------------------------
    | Configuration for validating incoming API sync requests on live server
    | Uses SYNC_API_TOKEN - same token name on both local and live servers
    |
    */
    'sync_api' => [
        'token' => env('SYNC_API_TOKEN'),
    ],

];

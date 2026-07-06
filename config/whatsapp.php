<?php

$config = [];

// Meta/Facebook WhatsApp Business API Configuration (if needed)
if(env('WHATSAPP_MODE' , NULL) == 'SANDBOX'){
    $config = [
        'verify_token' => env('VERIFY_TOKEN' , NULL),
        'whatsapp_phone_number_id' => env('PHONE_NUMBER_ID_SANDBOX' , NULL),
        'whatsapp_token' => env('WHATSAPP_TOKEN_SANDBOX' , NULL),
        'whatsapp_api_version'  => env('WHATSAPP_API_VERSION', 'v20.0')
    ];
} elseif(env('WHATSAPP_MODE', NULL) == 'LIVE'){
    $config = [
        'verify_token' => env('VERIFY_TOKEN' , NULL),
        'whatsapp_phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID' , NULL),
        'whatsapp_token'   => env('WHATSAPP_TOKEN' , NULL),
        'whatsapp_api_version'  => env('WHATSAPP_API_VERSION', 'v20.0')
    ];
}

// WhatsApp Intelligent API Configuration
$config['intelligent'] = [
    'api_url' => env('WHATSAPP_INTELLIGENT_API_URL', 'http://whatsintelligent.com/api/create-message'),
    'appkey' => env('WHATSAPP_INTELLIGENT_APPKEY', ''),
    'authkey' => env('WHATSAPP_INTELLIGENT_AUTHKEY', ''),
    'sandbox' => env('WHATSAPP_INTELLIGENT_SANDBOX', 'false'),
];

return $config;


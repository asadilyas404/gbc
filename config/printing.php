<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Printer Settings
    |--------------------------------------------------------------------------
    |
    | These are the default printer settings for the application.
    | You can override these in your .env file or by passing parameters
    | to the print commands.
    |
    */

    'default_printer' => env('DEFAULT_PRINTER', 'file:///dev/usb/lp0'),

    /*
    |--------------------------------------------------------------------------
    | Database Printer Settings
    |--------------------------------------------------------------------------
    |
    | Settings for using printers stored in the database.
    |
    */

    'database_printers' => [
        'enabled' => env('USE_DATABASE_PRINTERS', true),
        'default_type' => 'bill_print',
        'fallback_connection' => env('DEFAULT_PRINTER', 'file:///dev/usb/lp0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Print Settings
    |--------------------------------------------------------------------------
    |
    | Settings for the automatic order printing functionality.
    |
    */

    'auto_print' => [
        'enabled' => env('AUTO_PRINT_ENABLED', false),
        'interval_seconds' => env('AUTO_PRINT_INTERVAL', 10),
        'max_orders_per_run' => env('AUTO_PRINT_MAX_ORDERS', 10),
        'order_statuses' => ['pending', 'confirmed', 'processing'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Printer Connection Examples
    |--------------------------------------------------------------------------
    |
    | Examples of different printer connection strings:
    |
    | File printer (USB/Serial):
    | 'file:///dev/usb/lp0' (Linux)
    | 'file:///dev/ttyUSB0' (Linux Serial)
    | 'file://C:\temp\receipt.txt' (Windows - for testing)
    |
    | Network printer:
    | 'network://192.168.1.100:9100'
    | 'network://printer.local:9100'
    |
    */

    'printers' => [
        'kitchen' => env('KITCHEN_PRINTER', 'file:///dev/usb/lp0'),
        'receipt' => env('RECEIPT_PRINTER', 'file:///dev/usb/lp1'),
        'test' => env('TEST_PRINTER', 'file://C:\temp\receipt.txt'),
    ],
];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SFTP Transport
    |--------------------------------------------------------------------------
    | Credentials are read exclusively from environment variables.
    | Never hardcode values here.
    */
    'sftp' => [
        'host' => env('SFTP_HOST'),
        'port' => (int)env('SFTP_PORT', 22),
        'user' => env('SFTP_USER'),
        'password' => env('SFTP_PASSWORD'),
        'path' => env('SFTP_PATH', '/uploads/print'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Local Transport
    |--------------------------------------------------------------------------
    */
    'local' => [
        'export_dir' => env('PRINT_LOCAL_EXPORT_DIR', '/var/exports/print'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Operational Safeguards
    |--------------------------------------------------------------------------
    */
    'batch' => [
        'max_size' => (int)env('PRINT_BATCH_MAX_SIZE', 5000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    | When true, CSV output is written to application logs for inspection.
    | Must never be enabled in production.
    */
    'debug' => (bool)env('PRINT_EXPORT_DEBUG', false),

];
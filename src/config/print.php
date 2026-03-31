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
        'export_dir' => env('PRINT_LOCAL_EXPORT_DIR', __DIR__ . '/../storage/exports/print'),
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

    /*
   |--------------------------------------------------------------------------
   | Queue
   |--------------------------------------------------------------------------
   | All print pipeline jobs run on this queue.
   */
    'queue' => env('PRINT_QUEUE', 'print'),

    /*
    |--------------------------------------------------------------------------
    | Fulfilment chunking
    |--------------------------------------------------------------------------
    | Number of subscriptions processed per CreateFulfilmentsChunkJob.
    | Increase for lower queue pressure; decrease for finer retry granularity.
    */
    'chunk_size' => (int)env('PRINT_CHUNK_SIZE', 200),

    /*
    |--------------------------------------------------------------------------
    | Safety net monitor
    |--------------------------------------------------------------------------
    | Delay (minutes) before FulfilmentCompletionMonitorJob fires.
    | Should exceed the expected Phase 1 wall-clock time for the largest batch.
    */
    'monitor_delay_minutes' => (int)env('PRINT_MONITOR_DELAY_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Label format
    |--------------------------------------------------------------------------
    | Default label export format when none is specified.
    | Must match a value from App\Enums\Subscriptions\LabelExportFormat.
    */
    'label_format' => env('PRINT_LABEL_FORMAT', 'csv'),

    /*
    |--------------------------------------------------------------------------
    | Return address (printed on every label)
    |--------------------------------------------------------------------------
    */
    'return_address' => [
        'name' => env('PRINT_RETURN_NAME', 'Test'),
        'line_1' => env('PRINT_RETURN_LINE_1', 'test line 1'),
        'line_2' => env('PRINT_RETURN_LINE_2'),
        'city' => env('PRINT_RETURN_CITY', 'test'),
        'postcode' => env('PRINT_RETURN_POSTCODE', 'test'),
        'country' => env('PRINT_RETURN_COUNTRY', 'United Kingdom'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Label SFTP (can differ from batch SFTP)
    |--------------------------------------------------------------------------
    */
    'label_sftp' => [
        'host' => env('PRINT_LABEL_SFTP_HOST', env('PRINT_SFTP_HOST')),
        'port' => (int)env('PRINT_LABEL_SFTP_PORT', env('PRINT_SFTP_PORT', 22)),
        'user' => env('PRINT_LABEL_SFTP_USER', env('PRINT_SFTP_USER')),
        'password' => env('PRINT_LABEL_SFTP_PASSWORD', env('PRINT_SFTP_PASSWORD')),
        'path' => env('PRINT_LABEL_SFTP_PATH', '/labels'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Export limits
    |--------------------------------------------------------------------------
    */
    'max_batch_size' => (int)env('PRINT_MAX_BATCH_SIZE', 5000),

    /*
    |--------------------------------------------------------------------------
    | Label retry attempts
    |--------------------------------------------------------------------------
    */
    'label_max_attempts' => (int)env('PRINT_LABEL_MAX_ATTEMPTS', 3),
];
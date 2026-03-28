<?php

return [

    'fulfilment' => [

        /*
        |----------------------------------------------------------------------
        | Queue
        |----------------------------------------------------------------------
        | All product fulfilment pipeline jobs run on this queue.
        | Separate from the print queue so export volume on one pipeline
        | does not delay the other.
        */
        'queue' => env('PRODUCT_FULFILMENT_QUEUE', 'products'),

        /*
        |----------------------------------------------------------------------
        | Chunk size
        |----------------------------------------------------------------------
        | Number of order lines processed per CreateProductFulfilmentsChunkJob.
        | Smaller than print (100 vs 200) because product lines may carry
        | heavier address resolution (no subscription cache).
        */
        'chunk_size' => (int)env('PRODUCT_FULFILMENT_CHUNK_SIZE', 100),

        /*
        |----------------------------------------------------------------------
        | Safety net monitor
        |----------------------------------------------------------------------
        | Delay (minutes) before ProductFulfilmentMonitorJob fires.
        | Should exceed the expected Phase 1 wall-clock time for the largest order.
        */
        'monitor_delay_minutes' => (int)env('PRODUCT_FULFILMENT_MONITOR_DELAY_MINUTES', 15),

        /*
        |----------------------------------------------------------------------
        | Maximum batch size
        |----------------------------------------------------------------------
        | Hard ceiling on the number of fulfilments exported in one batch file.
        | Requests that exceed this are failed before transport so the queue
        | retry does not re-attempt an oversized payload.
        */
        'max_batch_size' => (int)env('PRODUCT_FULFILMENT_MAX_BATCH_SIZE', 5000),

        /*
        |----------------------------------------------------------------------
        | SFTP transport (production)
        |----------------------------------------------------------------------
        | Uses product-specific credentials so product and print exports can
        | route to different servers without coupling their configs.
        */
        'sftp' => [
            'host' => env('PRODUCT_SFTP_HOST'),
            'port' => (int)env('PRODUCT_SFTP_PORT', 22),
            'user' => env('PRODUCT_SFTP_USER'),
            'password' => env('PRODUCT_SFTP_PASSWORD'),
            'path' => env('PRODUCT_SFTP_PATH', '/uploads/products'),
        ],

        /*
        |----------------------------------------------------------------------
        | Local transport (development / staging)
        |----------------------------------------------------------------------
        */
        'local' => [
            'export_dir' => env('PRODUCT_LOCAL_EXPORT_DIR', storage_path('exports/products')),
        ],

    ],

];
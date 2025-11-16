<?php

return [
    'driver' => env('MAIL_DRIVER', 'log'), // smtp, log, array

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example App'),
    ],

    'smtp' => [
        'host' => env('MAIL_HOST', 'smtp.mailtrap.io'),
        'port' => env('MAIL_PORT', 587),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'username' => env('MAIL_USERNAME'),
        'password' => env('MAIL_PASSWORD'),
    ],
];
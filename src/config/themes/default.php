<?php

return [
    'name' => 'Default Theme',
    'description' => 'Clean and modern default theme',
    'version' => '1.0.0',
    'author' => 'Your Company',
    'preview' => '/assets/images/theme-previews/default.png',

    'colors' => [
        'primary' => '#007bff',
        'secondary' => '#6c757d',
        'success' => '#28a745',
        'danger' => '#dc3545',
        'warning' => '#ffc107',
        'info' => '#17a2b8',
        'light' => '#f8f9fa',
        'dark' => '#343a40'
    ],

    'fonts' => [
        'primary' => "'Roboto', sans-serif",
        'heading' => "'Montserrat', sans-serif",
        'monospace' => "'Courier New', monospace"
    ],

    'layout' => 'default',

    'features' => [
        'dark_mode',
        'responsive',
        'animations',
        'custom_fonts'
    ],

    'additional_css' => [
        'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&family=Montserrat:wght@600;700&display=swap'
    ],

    'js_files' => [
        '/assets/js/themes/default.js'
    ],

    'settings' => [
        'header_style' => 'fixed',
        'footer_style' => 'default',
        'sidebar_position' => 'right',
        'container_width' => '1200px'
    ]
];
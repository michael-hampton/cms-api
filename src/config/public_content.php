<?php

return [
    'widgets' => [
        'page-title' => ['page_types' => ['article']],
        'category-pills' => ['page_types' => ['article']],
        'tags' => ['page_types' => ['article']],
        'page-actions' => ['page_types' => ['article']],
        'most-popular-articles' => [
            'page_types' => ['landing-page'],
            'limit' => 6,
        ],
        'comments' => ['page_types' => ['article']],
    ],
];

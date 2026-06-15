<?php

use App\Controllers\Api\V1\PublicContentController;
use App\Framework\Http\Router;

/** @var Router $router */
$router->get(
    '/api/v1/{site}/content/{slug}',
    [PublicContentController::class, 'show'],
);

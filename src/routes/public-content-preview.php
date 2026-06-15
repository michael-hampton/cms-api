<?php

use App\Controllers\Front\PublicContentPreviewController;
use App\Framework\Http\Router;

/** @var Router $router */
$router->get(
    '/{site}/content-v2/{slug}',
    [PublicContentPreviewController::class, 'show'],
);

<?php

use App\Controllers\Front\PublicContentHomepagePreviewController;
use App\Controllers\Front\PublicContentPreviewController;
use App\Framework\Http\Router;

/** @var Router $router */
$router->get(
    '/{site}/content-v2',
    [PublicContentHomepagePreviewController::class, 'show'],
);

$router->get(
    '/{site}/content-v2/{slug}',
    [PublicContentPreviewController::class, 'show'],
);

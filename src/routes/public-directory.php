<?php

use App\Controllers\Api\V1\PublicDirectoryController;
use App\Controllers\Front\PublicDirectoryPageController;
use App\Framework\Http\Router;

/** @var Router $router */

$router->get('/{site}/authors', [PublicDirectoryPageController::class, 'index'], ['type' => 'author']);
$router->get('/{site}/authors/{slug}', [PublicDirectoryPageController::class, 'show'], ['type' => 'author']);
$router->get('/{site}/categories', [PublicDirectoryPageController::class, 'index'], ['type' => 'category']);
$router->get('/{site}/categories/{slug}', [PublicDirectoryPageController::class, 'show'], ['type' => 'category']);
$router->get('/{site}/tags', [PublicDirectoryPageController::class, 'index'], ['type' => 'tag']);
$router->get('/{site}/tags/{slug}', [PublicDirectoryPageController::class, 'show'], ['type' => 'tag']);

$router->get('/api/v1/{site}/directory/author', [PublicDirectoryController::class, 'index'], ['type' => 'author']);
$router->get('/api/v1/{site}/directory/author/{slug}', [PublicDirectoryController::class, 'show'], ['type' => 'author']);
$router->get('/api/v1/{site}/directory/category', [PublicDirectoryController::class, 'index'], ['type' => 'category']);
$router->get('/api/v1/{site}/directory/category/{slug}', [PublicDirectoryController::class, 'show'], ['type' => 'category']);
$router->get('/api/v1/{site}/directory/tag', [PublicDirectoryController::class, 'index'], ['type' => 'tag']);
$router->get('/api/v1/{site}/directory/tag/{slug}', [PublicDirectoryController::class, 'show'], ['type' => 'tag']);

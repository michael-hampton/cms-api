<?php

use App\Controllers\Api\V1\PublicDirectoryController;
use App\Controllers\Front\PublicDirectoryPageController;
use App\Framework\Http\Router;

/** @var Router $router */

$router->get('/{site}/authors', [PublicDirectoryPageController::class, 'authors']);
$router->get('/{site}/authors/{slug}', [PublicDirectoryPageController::class, 'author']);
$router->get('/{site}/categories', [PublicDirectoryPageController::class, 'categories']);
$router->get('/{site}/categories/{slug}', [PublicDirectoryPageController::class, 'category']);
$router->get('/{site}/tags', [PublicDirectoryPageController::class, 'tags']);
$router->get('/{site}/tags/{slug}', [PublicDirectoryPageController::class, 'tag']);

$router->get('/api/v1/{site}/directory/author', [PublicDirectoryController::class, 'authors']);
$router->get('/api/v1/{site}/directory/author/{slug}', [PublicDirectoryController::class, 'author']);
$router->get('/api/v1/{site}/directory/category', [PublicDirectoryController::class, 'categories']);
$router->get('/api/v1/{site}/directory/category/{slug}', [PublicDirectoryController::class, 'category']);
$router->get('/api/v1/{site}/directory/tag', [PublicDirectoryController::class, 'tags']);
$router->get('/api/v1/{site}/directory/tag/{slug}', [PublicDirectoryController::class, 'tag']);

<?php

use App\Controllers\Api\V1\PublicDirectoryController;
use App\Controllers\Front\PublicDirectoryPageController;
use App\Controllers\Members\Subscriptions\UnifiedMemberSubscriptionsController;
use App\Framework\Http\Router;

/** @var Router $router */

$router->get('/{site}/member/subscriptions/unified', UnifiedMemberSubscriptionsController::class);

$router->get('/{site}/content-v2/authors', [PublicDirectoryPageController::class, 'previewAuthors']);
$router->get('/{site}/content-v2/authors/{slug}', [PublicDirectoryPageController::class, 'previewAuthor']);
$router->get('/{site}/content-v2/categories', [PublicDirectoryPageController::class, 'previewCategories']);
$router->get('/{site}/content-v2/categories/{slug}', [PublicDirectoryPageController::class, 'previewCategory']);
$router->get('/{site}/content-v2/tags', [PublicDirectoryPageController::class, 'previewTags']);
$router->get('/{site}/content-v2/tags/{slug}', [PublicDirectoryPageController::class, 'previewTag']);

$router->get('/{site}/authors', [PublicDirectoryPageController::class, 'authors']);
$router->get('/{site}/authors/{slug}', [PublicDirectoryPageController::class, 'author']);
$router->get('/{site}/categories', [PublicDirectoryPageController::class, 'categories']);
$router->get('/{site}/categories/{slug}', [PublicDirectoryPageController::class, 'category']);
$router->get('/{site}/category/{slug}', [PublicDirectoryPageController::class, 'category']);
$router->get('/{site}/tags', [PublicDirectoryPageController::class, 'tags']);
$router->get('/{site}/tags/{slug}', [PublicDirectoryPageController::class, 'tag']);
$router->get('/{site}/tag/{slug}', [PublicDirectoryPageController::class, 'tag']);
$router->get('/{site}/author/{slug}', [PublicDirectoryPageController::class, 'author']);

$router->get('/api/v1/{site}/directory/author', [PublicDirectoryController::class, 'authors']);
$router->get('/api/v1/{site}/directory/author/{slug}', [PublicDirectoryController::class, 'author']);
$router->get('/api/v1/{site}/directory/category', [PublicDirectoryController::class, 'categories']);
$router->get('/api/v1/{site}/directory/category/{slug}', [PublicDirectoryController::class, 'category']);
$router->get('/api/v1/{site}/directory/tag', [PublicDirectoryController::class, 'tags']);
$router->get('/api/v1/{site}/directory/tag/{slug}', [PublicDirectoryController::class, 'tag']);

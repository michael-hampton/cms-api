<?php

use App\Controllers\Api\V1\PublicContentBadgeModalController;
use App\Controllers\Api\V1\PublicContentController;
use App\Controllers\Api\V1\PublicContentViewerController;
use App\Framework\Http\Router;

/** @var Router $router */
$router->get('/api/v1/{site}/content/{slug}', [PublicContentController::class, 'show']);
$router->get('/api/v1/{site}/regions/{regionSlug}/content/{slug}', [PublicContentController::class, 'showRegional']);
$router->get('/api/v1/{site}/content/{pageId}/viewer-state', [PublicContentViewerController::class, 'show']);
$router->put('/api/v1/{site}/content/{pageId}/like', [PublicContentViewerController::class, 'like']);
$router->delete('/api/v1/{site}/content/{pageId}/like', [PublicContentViewerController::class, 'unlike']);
$router->post('/api/v1/{site}/content/{pageId}/views', [PublicContentViewerController::class, 'recordView']);
$router->get('/api/v1/{site}/content/{pageId}/comments', [PublicContentViewerController::class, 'comments']);
$router->post('/api/v1/{site}/content/{pageId}/comments', [PublicContentViewerController::class, 'storeComment']);
$router->post('/api/v1/{site}/badge-modals/{memberBadgeId}/viewed', [PublicContentBadgeModalController::class, 'markViewed']);

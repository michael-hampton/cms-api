<?php

use App\Controllers\Api\V1\PublicContentBadgeModalController;
use App\Controllers\Api\V1\PublicContentController;
use App\Controllers\Api\V1\PublicContentViewerController;
use App\Framework\Http\Router;
use App\Framework\Middleware\VerifyCsrfToken;
use App\Middleware\PublicContent\MeasurePublicApiLatencyMiddleware;

/** @var Router $router */
$router->group([
    'middleware' => [MeasurePublicApiLatencyMiddleware::class],
], function (Router $router): void {
    $csrf = [VerifyCsrfToken::class];

    $router->get('/api/v1/{site}/content/{slug}', [PublicContentController::class, 'show']);
    $router->get('/api/v1/{site}/regions/{regionSlug}/content/{slug}', [PublicContentController::class, 'showRegional']);
    $router->get('/api/v1/{site}/content/{pageId}/viewer-state', [PublicContentViewerController::class, 'show']);
    $router->put('/api/v1/{site}/content/{pageId}/like', [PublicContentViewerController::class, 'like'], middleware: $csrf);
    $router->delete('/api/v1/{site}/content/{pageId}/like', [PublicContentViewerController::class, 'unlike'], middleware: $csrf);
    $router->post('/api/v1/{site}/content/{pageId}/views', [PublicContentViewerController::class, 'recordView']);
    $router->get('/api/v1/{site}/content/{pageId}/comments', [PublicContentViewerController::class, 'comments']);
    $router->post('/api/v1/{site}/content/{pageId}/comments', [PublicContentViewerController::class, 'storeComment'], middleware: $csrf);
    $router->post('/api/v1/{site}/badge-modals/{memberBadgeId}/viewed', [PublicContentBadgeModalController::class, 'markViewed'], middleware: $csrf);
});

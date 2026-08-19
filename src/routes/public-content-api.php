<?php

use App\Controllers\Api\V1\PublicContentBadgeModalController;
use App\Controllers\Api\V1\PublicContentController;
use App\Controllers\Api\V1\PublicContentImageController;
use App\Controllers\Api\V1\PublicContentPageWidgetController;
use App\Controllers\Api\V1\PublicContentViewerController;
use App\Controllers\Front\ConfigEditorController;
use App\Controllers\Front\PublicContentDiagnosticsDashboardController;
use App\Controllers\SiteController;
use App\Framework\Http\Router;
use App\Framework\Middleware\VerifyCsrfToken;
use App\Middleware\PublicContent\MeasurePublicApiLatencyMiddleware;
use App\Middleware\PublicContent\PublicApiCorsMiddleware;
use App\Middleware\PublicContent\PublicApiRateLimitMiddleware;
use App\Middleware\PublicContent\RequireMemberAuthMiddleware;
use App\Middleware\PublicContent\SecurityHeadersMiddleware;
use App\Middleware\PublicContent\ValidatePublicApiQueryMiddleware;

/** @var Router $router */
$router->group([
    'middleware' => [
        SecurityHeadersMiddleware::class,
        PublicApiCorsMiddleware::class,
        PublicApiRateLimitMiddleware::class,
        ValidatePublicApiQueryMiddleware::class,
        MeasurePublicApiLatencyMiddleware::class,
    ],
], function (Router $router): void {
    $csrf = [VerifyCsrfToken::class];
    $memberMutation = [RequireMemberAuthMiddleware::class, VerifyCsrfToken::class];

    // In your API/Web routing file:
    $router->get('/api/v1/{site}/content/site-config/{type}', [SiteController::class, 'getConfig']);
    $router->put('/api/v1/{site}/content/site-config/{type}', [SiteController::class, 'saveConfig']);
    $router->get('/{site}/public/config', [ConfigEditorController::class, 'show']);
    $router->get('/api/v1/{site}/content/config/{type}', [\App\Controllers\Api\V1\ConfigApiController::class, 'show']);
    $router->put('/api/v1/{site}/content/config/{type}', [\App\Controllers\Api\V1\ConfigApiController::class, 'update']);
    $router->get('/public/images/fallback', [PublicContentImageController::class, 'fallback']);
    $router->get('/public/images/{token}', [PublicContentImageController::class, 'show']);
    $router->get('/api/v1/{site}/content/{contentPath}', [PublicContentController::class, 'show'])
        ->where('contentPath', '(?![^/]+/(?:viewer-state|comments|widgets)$).+');
    $router->get('/api/v1/{site}/regions/{regionSlug}/content/{contentPath}', [PublicContentController::class, 'showRegional'])
        ->where('contentPath', '.+');
    $router->get('/api/v1/{site}/content/{pageId}/viewer-state', [PublicContentViewerController::class, 'show']);
    $router->get('/api/v1/{site}/content/{pageId}/widgets', [PublicContentPageWidgetController::class, 'index']);
    $router->put('/api/v1/{site}/content/{pageId}/widgets', [PublicContentPageWidgetController::class, 'update'], middleware: $csrf);
    $router->put('/api/v1/{site}/content/{pageId}/like', [PublicContentViewerController::class, 'like'], middleware: $memberMutation);
    $router->delete('/api/v1/{site}/content/{pageId}/like', [PublicContentViewerController::class, 'unlike'], middleware: $memberMutation);
    $router->post('/api/v1/{site}/content/{pageId}/views', [PublicContentViewerController::class, 'recordView']);
    $router->get('/api/v1/{site}/content/{pageId}/comments', [PublicContentViewerController::class, 'comments']);
    $router->post('/api/v1/{site}/content/{pageId}/comments', [PublicContentViewerController::class, 'storeComment'], middleware: $csrf);
    $router->post('/api/v1/{site}/badge-modals/{memberBadgeId}/viewed', [PublicContentBadgeModalController::class, 'markViewed'], middleware: $memberMutation);
    $router->get('/{site}/internal/public-content-v2/diagnostics', [PublicContentDiagnosticsDashboardController::class, 'show']);

});

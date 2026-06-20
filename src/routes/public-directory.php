<?php

use App\Controllers\Api\V1\PublicDirectoryController;
use App\Controllers\Front\PublicDirectoryPageController;
use App\Controllers\Members\Subscriptions\UnifiedMemberSubscriptionsController;
use App\Controllers\Subscription\ShopAccountApiController;
use App\Controllers\Subscription\ShopAccountBillingDatePreviewController;
use App\Controllers\Subscription\ShopAccountBillingDateUpdateController;
use App\Controllers\Subscription\ShopAccountController;
use App\Controllers\Subscription\ShopAccountDeliveryAddressController;
use App\Controllers\Subscription\ShopAccountDeliveryController;
use App\Controllers\Subscription\ShopAccountIssueDeliveryController;
use App\Controllers\Subscription\ShopAccountSubscriptionHistoryController;
use App\Controllers\Subscription\ShopAccountSubscriptionPreferenceController;
use App\Controllers\Subscription\ShopAccountSubscriptionSettingsController;
use App\Controllers\Subscription\ShopAccountSubscriptionUpgradeController;
use App\Framework\Http\Router;
use App\Framework\Middleware\AuthenticateMemberWithToken;
use App\Framework\Middleware\VerifyCsrfToken;

/** @var Router $router */

$auth = [AuthenticateMemberWithToken::class];
$write = [AuthenticateMemberWithToken::class, VerifyCsrfToken::class];

$router->get('/{site}/member/subscriptions/unified', UnifiedMemberSubscriptionsController::class, middleware: $auth);
$router->post('/{site}/member/subscriptions/{id}/cancel', [ShopAccountApiController::class, 'cancelSubscription'], middleware: $write);
$router->post('/{site}/member/subscriptions/{id}/reactivate', [ShopAccountApiController::class, 'reactivateSubscription'], middleware: $write);
$router->post('/{site}/member/subscriptions/{id}/pause', [ShopAccountApiController::class, 'pauseSubscription'], middleware: $write);
$router->post('/{site}/member/subscriptions/{id}/resume', [ShopAccountApiController::class, 'resumeSubscription'], middleware: $write);
$router->get('/{site}/member/subscriptions/{id}/renew', [ShopAccountController::class, 'renew'], middleware: $auth);
$router->get('/{site}/member/subscriptions/{id}/resubscribe', [ShopAccountController::class, 'resubscribe'], middleware: $auth);
$router->get('/{site}/member/subscriptions/{id}/settle-payment', [ShopAccountApiController::class, 'settlePayment'], middleware: $auth);
$router->post('/{site}/member/subscriptions/{id}/auto-renew', [ShopAccountSubscriptionSettingsController::class, 'updateAutoRenew'], middleware: $write);
$router->post('/{site}/member/subscriptions/{id}/billing-date/preview', ShopAccountBillingDatePreviewController::class, middleware: $write);
$router->post('/{site}/member/subscriptions/{id}/billing-date', ShopAccountBillingDateUpdateController::class, middleware: $write);
$router->get('/{site}/member/subscriptions/{id}/history', ShopAccountSubscriptionHistoryController::class, middleware: $auth);
$router->get('/{site}/member/subscriptions/{id}/delivery', [ShopAccountDeliveryController::class, 'status'], middleware: $auth);
$router->post('/{site}/member/subscriptions/{id}/delivery/pause', [ShopAccountDeliveryController::class, 'pause'], middleware: $write);
$router->post('/{site}/member/subscriptions/{id}/delivery/resume', [ShopAccountDeliveryController::class, 'resume'], middleware: $write);
$router->get('/{site}/member/subscriptions/{id}/upgrades', [ShopAccountSubscriptionUpgradeController::class, 'options'], middleware: $auth);
$router->post('/{site}/member/subscriptions/{id}/upgrades/preview', [ShopAccountSubscriptionUpgradeController::class, 'preview'], middleware: $write);
$router->post('/{site}/member/subscriptions/{id}/upgrades', [ShopAccountSubscriptionUpgradeController::class, 'upgrade'], middleware: $write);
$router->get('/{site}/member/subscriptions/{id}/preferences', [ShopAccountSubscriptionPreferenceController::class, 'show'], middleware: $auth);
$router->post('/{site}/member/subscriptions/{id}/preferences', [ShopAccountSubscriptionPreferenceController::class, 'update'], middleware: $write);
$router->get('/{site}/member/subscriptions/{id}/delivery-addresses', [ShopAccountDeliveryAddressController::class, 'index'], middleware: $auth);
$router->post('/{site}/member/subscriptions/{id}/delivery-addresses/{addressId}/default', [ShopAccountDeliveryAddressController::class, 'setDefault'], middleware: $write);
$router->get('/{site}/member/subscriptions/{id}/issue-deliveries', ShopAccountIssueDeliveryController::class, middleware: $auth);

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

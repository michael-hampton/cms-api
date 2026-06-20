<?php

use App\Controllers\Members\Subscriptions\UnifiedMemberSubscriptionContinuationController;
use App\Controllers\Members\Subscriptions\UnifiedMemberSubscriptionsController;
use App\Controllers\Subscription\ShopAccountApiController;
use App\Controllers\Subscription\ShopAccountBillingDatePreviewController;
use App\Controllers\Subscription\ShopAccountBillingDateUpdateController;
use App\Controllers\Subscription\ShopAccountDeliveryAddressController;
use App\Controllers\Subscription\ShopAccountDeliveryController;
use App\Controllers\Subscription\ShopAccountIssueDeliveryController;
use App\Controllers\Subscription\ShopAccountSubscriptionHistoryController;
use App\Controllers\Subscription\ShopAccountSubscriptionPreferenceController;
use App\Controllers\Subscription\ShopAccountSubscriptionSettingsController;
use App\Controllers\Subscription\ShopAccountSubscriptionUpgradeController;
use App\Framework\Http\Router;
use App\Framework\Middleware\AuthenticateMemberWithToken;
use App\Framework\Middleware\RequireSubscriptionAccountAccess;
use App\Framework\Middleware\VerifyCsrfToken;

/** @var Router $router */

$auth = [AuthenticateMemberWithToken::class];
$owned = [AuthenticateMemberWithToken::class, RequireSubscriptionAccountAccess::class];
$write = [AuthenticateMemberWithToken::class, RequireSubscriptionAccountAccess::class, VerifyCsrfToken::class];
$base = '/{site}/member/subscriptions/unified';

$router->get($base, UnifiedMemberSubscriptionsController::class, middleware: $auth);
$router->post($base . '/{id}/cancel', [ShopAccountApiController::class, 'cancelSubscription'], middleware: $write);
$router->post($base . '/{id}/reactivate', [ShopAccountApiController::class, 'reactivateSubscription'], middleware: $write);
$router->post($base . '/{id}/pause', [ShopAccountApiController::class, 'pauseSubscription'], middleware: $write);
$router->post($base . '/{id}/resume', [ShopAccountApiController::class, 'resumeSubscription'], middleware: $write);
$router->get($base . '/{id}/renew', [UnifiedMemberSubscriptionContinuationController::class, 'renew'], middleware: $owned);
$router->get($base . '/{id}/resubscribe', [UnifiedMemberSubscriptionContinuationController::class, 'resubscribe'], middleware: $owned);
$router->get($base . '/{id}/settle-payment', [ShopAccountApiController::class, 'settlePayment'], middleware: $owned);
$router->post($base . '/{id}/auto-renew', [ShopAccountSubscriptionSettingsController::class, 'updateAutoRenew'], middleware: $write);
$router->post($base . '/{id}/billing-date/preview', ShopAccountBillingDatePreviewController::class, middleware: $write);
$router->post($base . '/{id}/billing-date', ShopAccountBillingDateUpdateController::class, middleware: $write);
$router->get($base . '/{id}/history', ShopAccountSubscriptionHistoryController::class, middleware: $owned);
$router->get($base . '/{id}/delivery', [ShopAccountDeliveryController::class, 'status'], middleware: $owned);
$router->post($base . '/{id}/delivery/pause', [ShopAccountDeliveryController::class, 'pause'], middleware: $write);
$router->post($base . '/{id}/delivery/resume', [ShopAccountDeliveryController::class, 'resume'], middleware: $write);
$router->get($base . '/{id}/upgrades', [ShopAccountSubscriptionUpgradeController::class, 'options'], middleware: $owned);
$router->post($base . '/{id}/upgrades/preview', [ShopAccountSubscriptionUpgradeController::class, 'preview'], middleware: $write);
$router->post($base . '/{id}/upgrades', [ShopAccountSubscriptionUpgradeController::class, 'upgrade'], middleware: $write);
$router->get($base . '/{id}/preferences', [ShopAccountSubscriptionPreferenceController::class, 'show'], middleware: $owned);
$router->post($base . '/{id}/preferences', [ShopAccountSubscriptionPreferenceController::class, 'update'], middleware: $write);
$router->get($base . '/{id}/delivery-addresses', [ShopAccountDeliveryAddressController::class, 'index'], middleware: $owned);
$router->post($base . '/{id}/delivery-addresses/{addressId}/default', [ShopAccountDeliveryAddressController::class, 'setDefault'], middleware: $write);
$router->get($base . '/{id}/issue-deliveries', ShopAccountIssueDeliveryController::class, middleware: $owned);

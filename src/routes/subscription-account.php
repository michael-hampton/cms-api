<?php

use App\Controllers\Members\Subscriptions\UnifiedMemberSubscriptionContinuationController as Continuation;
use App\Controllers\Members\Subscriptions\UnifiedMemberSubscriptionsController as Unified;
use App\Controllers\Subscription\ShopAccountApiController as Api;
use App\Controllers\Subscription\ShopAccountBillingDatePreviewController as BillingPreview;
use App\Controllers\Subscription\ShopAccountBillingDateUpdateController as BillingUpdate;
use App\Controllers\Subscription\ShopAccountDeliveryAddressController as DeliveryAddress;
use App\Controllers\Subscription\ShopAccountDeliveryController as Delivery;
use App\Controllers\Subscription\ShopAccountIssueDeliveryController as IssueDeliveries;
use App\Controllers\Subscription\ShopAccountResubscribeController as Resubscribe;
use App\Controllers\Subscription\ShopAccountSubscriptionHistoryController as History;
use App\Controllers\Subscription\ShopAccountSubscriptionPreferenceController as Preferences;
use App\Controllers\Subscription\ShopAccountSubscriptionSettingsController as Settings;
use App\Controllers\Subscription\ShopAccountSubscriptionUpgradeController as Upgrades;
use App\Framework\Middleware\AuthenticateMemberWithToken as Auth;
use App\Framework\Middleware\RequireSubscriptionAccountAccess as Owns;
use App\Framework\Middleware\VerifyCsrfToken as Csrf;

$auth = [Auth::class];
$owned = [Auth::class, Owns::class];
$write = [Auth::class, Owns::class, Csrf::class];
$base = '/{site}/member/subscriptions/unified';

$router->get($base, Unified::class, middleware: $auth);
$router->post($base . '/{id}/cancel', [Api::class, 'cancelSubscription'], middleware: $write);
$router->post($base . '/{id}/reactivate', [Api::class, 'reactivateSubscription'], middleware: $write);
$router->post($base . '/{id}/pause', [Api::class, 'pauseSubscription'], middleware: $write);
$router->post($base . '/{id}/resume', [Api::class, 'resumeSubscription'], middleware: $write);
$router->get($base . '/{id}/renew', [Continuation::class, 'renew'], middleware: $owned);
$router->post($base . '/{id}/resubscribe', Resubscribe::class, middleware: $write);
$router->get($base . '/{id}/resubscribe', [Continuation::class, 'resubscribe'], middleware: $owned);
$router->get($base . '/{id}/settle-payment', [Api::class, 'settlePayment'], middleware: $owned);
$router->post($base . '/{id}/auto-renew', [Settings::class, 'updateAutoRenew'], middleware: $write);
$router->post($base . '/{id}/billing-date/preview', BillingPreview::class, middleware: $write);
$router->post($base . '/{id}/billing-date', BillingUpdate::class, middleware: $write);
$router->get($base . '/{id}/history', History::class, middleware: $owned);
$router->get($base . '/{id}/delivery', [Delivery::class, 'status'], middleware: $owned);
$router->post($base . '/{id}/delivery/pause', [Delivery::class, 'pause'], middleware: $write);
$router->post($base . '/{id}/delivery/resume', [Delivery::class, 'resume'], middleware: $write);
$router->get($base . '/{id}/upgrades', [Upgrades::class, 'options'], middleware: $owned);
$router->post($base . '/{id}/upgrades/preview', [Upgrades::class, 'preview'], middleware: $write);
$router->post($base . '/{id}/upgrades', [Upgrades::class, 'upgrade'], middleware: $write);
$router->get($base . '/{id}/preferences', [Preferences::class, 'show'], middleware: $owned);
$router->post($base . '/{id}/preferences', [Preferences::class, 'update'], middleware: $write);
$router->get($base . '/{id}/delivery-addresses', [DeliveryAddress::class, 'index'], middleware: $owned);
$router->post($base . '/{id}/delivery-addresses/{addressId}/default', [DeliveryAddress::class, 'setDefault'], middleware: $write);
$router->get($base . '/{id}/issue-deliveries', IssueDeliveries::class, middleware: $owned);
$router->post('/press-stack/account/subscriptions/{id}/resubscribe', Resubscribe::class, middleware: [Auth::class, Csrf::class]);

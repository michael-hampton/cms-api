<?php

use App\Controllers\Admin\AdminSubscriptionPlansController;
use App\Controllers\Cms\BlockController;
use App\Controllers\Cms\PageController;
use App\Controllers\Cms\PaymentController;
use App\Controllers\EventController;
use App\Controllers\FaqController;
use App\Controllers\Front\AuthorViewController;
use App\Controllers\Front\BrandPageController;
use App\Controllers\Front\BundleListController;
use App\Controllers\Front\BuyingGuideController;
use App\Controllers\Front\CartController;
use App\Controllers\Front\CategoryPageController;
use App\Controllers\Front\CommentController;
use App\Controllers\Front\ContentController;
use App\Controllers\Front\DealsController;
use App\Controllers\Front\EstateWebsiteController;
use App\Controllers\Front\OfferListController;
use App\Controllers\Front\PageLikeController;
use App\Controllers\Front\ProductDetailController;
use App\Controllers\Front\ProductListController;
use App\Controllers\Front\RegionContentController;
use App\Controllers\Front\ReviewPageController;
use App\Controllers\Front\TagViewController;
use App\Controllers\Front\WebPageController;
use App\Controllers\Front\WishlistController;
use App\Controllers\MemberAuthController;
use App\Controllers\MemberController;
use App\Controllers\Members\GiftedArticlesController;
use App\Controllers\Members\MemberActivityController;
use App\Controllers\Members\MemberAddressController;
use App\Controllers\Members\MemberBadgeController;
use App\Controllers\Members\MemberCommentsController;
use App\Controllers\Members\MemberConsentController;
use App\Controllers\Members\MemberDashboardController;
use App\Controllers\Members\MemberInvoiceController;
use App\Controllers\Members\MemberLikedPagesController;
use App\Controllers\Members\MemberOrdersController;
use App\Controllers\Members\MemberPaymentMethodsController;
use App\Controllers\Members\MemberReadingHistoryController;
use App\Controllers\Members\MemberSupportController;
use App\Controllers\Members\MemberWishlistController;
use App\Controllers\Members\Newsletters\MemberNewslettersController;
use App\Controllers\Members\RewardsController;
use App\Controllers\Members\Subscriptions\MemberIssueDeliveriesController;
use App\Controllers\Members\Subscriptions\MemberSubscriptionPaymentsController;
use App\Controllers\Members\Subscriptions\MemberSubscriptionPlansController;
use App\Controllers\Members\Subscriptions\MemberSubscriptionsController;
use App\Controllers\Members\Subscriptions\MemberSubscriptionUpgradeController;
use App\Controllers\Members\Subscriptions\SingleContentAccessController;
use App\Controllers\Newsletter\NewsletterController;
use App\Controllers\Newsletter\NewsletterWebController;
use App\Controllers\Offers\ProductOfferController;
use App\Controllers\Product\ProductComparisonController;
use App\Controllers\Subscription\OneTimeSubscriptionsController;
use App\Controllers\Subscription\SubscriptionModalController;
use App\Framework\Middleware\RequireMemberAuth;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here are the API routes for your application. These routes are loaded
| by the RouteLoader within a group that is assigned the "api" prefix.
| All routes return JSON responses.
|
*/

// Pages API


// Web routes (return Response objects -> HTML)
$router->get('/', WebPageController::class, 'index');
$router->get('/pages', WebPageController::class, 'index');
$router->get('/pages/search', WebPageController::class, 'search');
$router->get('/test', WebPageController::class, 'test');
$router->get('/pages/create', WebPageController::class, 'create');
$router->post('/pages', WebPageController::class, 'store');
$router->get('/pages/{id}', WebPageController::class, 'show');
$router->get('/pages/{id}/edit', WebPageController::class, 'edit');
$router->put('/pages/{id}', WebPageController::class, 'update');
$router->delete('/pages/{id}', WebPageController::class, 'destroy');

$router->get('/{site}/offers', [OfferListController::class, 'index']);
$router->get('/{site}/offers/{offerId}', [OfferListController::class, 'show']);
$router->get('{site}/product-offers/search', [OfferListController::class, 'search']);


// Bundles Routes
$router->get('/{site}/bundles', [BundleListController::class, 'indexPage']);
$router->get('/{site}/bundles/{bundleId}', [BundleListController::class, 'showPage']);
$router->get('/{site}/bundles/search', [BundleListController::class, 'searchBundles']);


// Block routes
$router->get('/blocks/{id}', BlockController::class, 'show');
$router->put('/blocks/{id}', BlockController::class, 'update');
$router->delete('/blocks/{id}', BlockController::class, 'destroy');
$router->get('/blocks/type/{type}', BlockController::class, 'getByType');

//$router->get('/estate', EstateWebsiteController::class, 'homepage');
//$router->get('/about', EstateWebsiteController::class, 'about');
//$router->get('/contact', EstateWebsiteController::class, 'contact');
$router->post('/contact', EstateWebsiteController::class, 'submitContact');
//$router->get('/properties', EstateWebsiteController::class, 'properties');
$router->get('/property/{id}', EstateWebsiteController::class, 'property');
$router->get('/{siteName}/category/{slug}', CategoryPageController::class, 'show')->name('category.show');
$router->get('/{siteName}/brand/{slug}', BrandPageController::class, 'show')->name('brand.show');
$router->post('/event-signup', EventController::class, 'signup');
$router->post('/{site}/comments', CommentController::class, 'store');

$router->get('/{site}/member/activity', [MemberActivityController::class, 'index']);
$router->get('/{site}/member/activity/badges', [MemberActivityController::class, 'badges']);
$router->get('/{site}/member/account-details', [MemberController::class, 'accountDetails']);
$router->post('/{site}/member/account-details', [MemberController::class, 'updateAccountDetails']);
$router->get('/{site}/member/new-badges', [MemberBadgeController::class, 'getNewBadges']);
$router->post('/{site}/member/badge-shown', [MemberBadgeController::class, 'markBadgeShown']);

$router->get('/{site}/member/rewards', [RewardsController::class, 'index']);
$router->get('/{site}/member/rewards/{id}', [RewardsController::class, 'show']); // View single reward details
$router->post('/{site}/member/rewards/{rewardId}/claim', [RewardsController::class, 'claim']);

$router->post('/{site}/member/rewards/{rewardId}/track/{action}', [RewardsController::class, 'trackClick']);
$router->post('/{site}/member/badge-modal-shown', [MemberBadgeController::class, 'markAsShown']);

$router->get('/{site}/member/gifted-articles', [GiftedArticlesController::class, 'index']);
$router->get('/{site}/gift-article/{pageSlug}', [GiftedArticlesController::class, 'showGiftForm']);
$router->post('/{site}/gift-article/{pageSlug}', [GiftedArticlesController::class, 'giftArticle']);
$router->get('/{site}/gift/{token}', [GiftedArticlesController::class, 'claim']);
$router->get('/{site}/member/gift-modal/{pageSlug}', [GiftedArticlesController::class, 'getGiftModal']);

$router->get('/{site}/reviews', [ReviewPageController::class, 'index']);
$router->get('/{site}/buying-guides', [BuyingGuideController::class, 'index']);

$router->get('/{site}/authors/{slug}', AuthorViewController::class, 'show');
$router->get('/{siteName}/tags/{slug}', [TagViewController::class, 'show']);

$router->get('/{site}/member/reading-history', [MemberReadingHistoryController::class, 'index']);
$router->get('/{site}/member/liked-pages', [MemberLikedPagesController::class, 'index']);

$router->get('{siteName}/shop', ProductListController::class, 'index');
$router->get('{siteName}/shop/details/{slug}', ProductDetailController::class, 'show');
$router->get('/sites', ContentController::class, 'sites');

$router->get('/member/register', [MemberAuthController::class, 'showRegisterForm']);
$router->get('/{siteName}/member/register', [MemberAuthController::class, 'showRegisterForm']);
$router->get('/{siteName}/member/login', [MemberAuthController::class, 'showLoginForm']);
$router->post('/member/logout', [MemberAuthController::class, 'logout']);

$router->get('/api/{siteName}/pages/search', [PageController::class, 'searchPages']);


// Email verification routes
$router->get('/member/verify-email-sent', [MemberAuthController::class, 'showVerifyEmailSent']);
$router->get('/verify-email', [MemberAuthController::class, 'verifyEmail']);

// Password reset routes
$router->get('/member/forgot-password', [MemberAuthController::class, 'showForgotPasswordForm'])
    ->name('member.forgot-password');

$router->get('/member/reset-password', [MemberAuthController::class, 'showResetPasswordForm'])
    ->name('member.reset-password');



$router->group(['middleware' => [\App\Framework\Middleware\VerifyCsrfToken::class]], function($router) {
    $router->post('/member/forgot-password', [MemberAuthController::class, 'sendPasswordResetEmail'])
        ->name('member.forgot-password.send');

    $router->post('/member/reset-password', [MemberAuthController::class, 'resetPassword'])
        ->name('member.reset-password.update');

    $router->post('/member/login', [MemberAuthController::class, 'login'])
        ->name('member.login.submit');

    $router->post('/{siteName}/member/login', [MemberAuthController::class, 'login'])
        ->name('member.login.submit');

    $router->post('/member/register', [MemberAuthController::class, 'register'])
        ->name('member.register');
    $router->post('/{siteName}/member/register', [MemberAuthController::class, 'register'])
        ->name('member.register');

    $router->post('/member/change-password', [MemberAuthController::class, 'changePassword'])
        ->name('member.change-password.update');
});

// Protected member routes
$router->group(['middleware' => [RequireMemberAuth::class]], function($router) {
    $router->get('/member/dashboard', [MemberAuthController::class, 'dashboard']);

    $router->get('/member/change-password', [MemberAuthController::class, 'showChangePasswordForm'])
        ->name('member.change-password');
});

// Member Consent Management Routes (Protected - requires member auth)
// Consent Preferences Page (HTML)
$router->get('/{site}/member/consent', [MemberConsentController::class, 'index']);
$router->get('/{site}/member/consent/audit-trail', 'App\Controllers\Members\MemberConsentController@auditTrail');
$router->get('/{site}/member/consent/download-data', 'App\Controllers\Members\MemberConsentController@downloadData');

// Consent API endpoints (JSON)
$router->post('/{site}/member/consent/update', 'App\Controllers\Members\MemberConsentController@update');
$router->post('/{site}/member/consent/grant/{consentCode}', 'App\Controllers\Members\MemberConsentController@grant');
$router->post('/{site}/member/consent/revoke/{consentCode}', 'App\Controllers\Members\MemberConsentController@revoke');
$router->post('/{site}/member/consent/withdrawal-request', 'App\Controllers\Members\MemberConsentController@createWithdrawalRequest');
$router->post('/{site}/member/consent/accept-banner', 'App\Controllers\Members\MemberConsentController@acceptBanner');
$router->get('/{site}/member/consent/check/{consentCode}', 'App\Controllers\Members\MemberConsentController@checkConsent');
$router->get('/api/{siteName}/consent/types/optional', 'App\Controllers\Members\MemberConsentController@getOptionalConsentTypes');


$router->get('/cart', [CartController::class, 'page']);
$router->get('/wishlist', [WishlistController::class, 'page']);
$router->post('/{site}/wishlist/bundle', [WishlistController::class, 'addBundle']);
$router->post('/{site}/wishlist/offer', [WishlistController::class, 'addOffer']);
$router->get('/{site}/cart', [CartController::class, 'page']);

// API routes for Cart (JSON responses)
$router->get('/api/{site}/cart', [CartController::class, 'index']);
$router->post('/api/{site}/cart', [CartController::class, 'add']);
$router->post('/api/{site}/cart/bundle', [CartController::class, 'addBundle']);
$router->post('/api/{site}/cart/offer', [CartController::class, 'addOffer']);
$router->put('/api/{site}/cart/{id}', [CartController::class, 'update']);
$router->delete('/api/{site}/cart/{id}', [CartController::class, 'remove']);
$router->delete('/api/{site}/cart/clear', [CartController::class, 'clear']);

$router->get('/checkout', [CartController::class, 'checkoutPage']);
$router->get('/{siteName}/checkout', [CartController::class, 'checkoutPage']);
$router->post('/api/{site}/checkout/process', [CartController::class, 'processCheckout']);
$router->post('/{site}/member/subscriptions/{id}/reactivate', [MemberSubscriptionsController::class, 'reactivate']);
$router->get('/{site}/member/subscriptions/{subscriptionId}/issue-deliveries', [MemberIssueDeliveriesController::class, 'index']);

$router->get('/{site}/member/subscriptions/{subscriptionId}/upgrade', [MemberSubscriptionUpgradeController::class, 'index']);
$router->post('/{site}/member/subscriptions/{subscriptionId}/upgrade', [MemberSubscriptionUpgradeController::class, 'upgrade']);
$router->post('/{site}/member/subscriptions/{subscriptionId}/upgrade/preview', [MemberSubscriptionUpgradeController::class, 'preview']);



$router->post('/{site}/member/subscriptions/{subscriptionId}/update-billing-date', [MemberSubscriptionsController::class, 'updateBillingDate']);
$router->post('/{site}/member/subscriptions/{subscriptionId}/preview-billing-change', [MemberSubscriptionsController::class, 'previewBillingDateChange']);

$router->get('/{site}/member/profile/communication-preferences', [MemberController::class, 'communicationPreferences']);
$router->post('/{site}/member/profile/communication-preferences', [MemberController::class, 'updateCommunicationPreferences']);

// Delivery Pause/Resume
$router->post('/{site}/member/subscriptions/{subscriptionId}/pause-delivery', [MemberSubscriptionsController::class, 'pauseDelivery']);
$router->post('/{site}/member/subscriptions/{subscriptionId}/resume-delivery', [MemberSubscriptionsController::class, 'resumeDelivery']);
$router->get('/{site}/member/subscriptions/{subscriptionId}/pause-status', [MemberSubscriptionsController::class, 'getPauseStatus']);

$router->post('/{site}/products/{productId}/offers/{offerId}/track', [ProductOfferController::class, 'trackClick']);

// API routes for Wishlist (JSON responses)
$router->get('/api/{site}/wishlist', [WishlistController::class, 'index']);
$router->post('/api/{site}/wishlist', [WishlistController::class, 'add']);
$router->delete('/api/{site}/wishlist/{productId}', [WishlistController::class, 'remove']);

$router->get('/order-confirmation', [CartController::class, 'orderConfirmation']);

$router->get('/{site}/member/addresses', [MemberAddressController::class, 'index']);
$router->get('/{site}/member/addresses/search', [MemberAddressController::class, 'search']);
$router->get('/{site}/member/addresses/create', [MemberAddressController::class, 'create']);
$router->get('/{site}/member/{memberId}/addresses', [MemberAddressController::class, 'show']);
$router->post('/{site}/member/addresses', [MemberAddressController::class, 'store']);
$router->get('/{site}/member/addresses/{id}/edit', [MemberAddressController::class, 'edit']);
$router->put('/{site}/member/addresses/{id}', [MemberAddressController::class, 'update']);
$router->delete('/{site}/member/addresses/{id}', [MemberAddressController::class, 'destroy']);
$router->post('/{site}/member/addresses/{id}', [MemberAddressController::class, 'update']); // If you don't support PUT
$router->post('/{site}/member/addresses/{id}/delete', [MemberAddressController::class, 'destroy']);
$router->post('/{site}/member/addresses/{id}/set-default', [MemberAddressController::class, 'setDefault']);

$router->get('/member/me', MemberController::class, 'me');

$router->post('/{site}/pages/like/{pageId}', [PageLikeController::class, 'toggle']);

$router->get('/{site}/member/dashboard', [MemberDashboardController::class, 'index']);

// Member Orders Routes
$router->get('/{site}/member/orders', [MemberOrdersController::class, 'index']);
$router->get('/{site}/member/orders/{orderId}', [MemberOrdersController::class, 'show']);
$router->post('/{site}/member/orders/{id}/cancel', [MemberOrdersController::class, 'cancel']);


// Member Subscriptions Routes
$router->get('/{site}/member/subscriptions', [MemberSubscriptionsController::class, 'index']);
$router->get('/{site}/member/subscriptions/preferences', [MemberSubscriptionsController::class, 'preferences']);
$router->post('/{site}/member/subscriptions/preferences', [MemberSubscriptionsController::class, 'updatePreferences']);
$router->get('/{site}/member/subscriptions/unsubscribe/{token}', [MemberSubscriptionsController::class, 'unsubscribeForm']);
$router->post('/{site}/member/subscriptions/unsubscribe/{token}', [MemberSubscriptionsController::class, 'unsubscribe']);
$router->post('/{site}/member/subscriptions/resubscribe/{token}', [MemberSubscriptionsController::class, 'resubscribe']);
$router->post('/{site}/member/subscriptions/{subscriptionId}/cancel', [MemberSubscriptionsController::class, 'cancel']);

$router->get('/{site}/member/subscriptions/manage/{token}', [MemberSubscriptionsController::class, 'manageByToken']);
$router->post('/{site}/member/subscriptions/manage/{token}', [MemberSubscriptionsController::class, 'updateByToken']);

$router->get('/{site}/member/subscription-plans', [MemberSubscriptionPlansController::class, 'index']);
$router->get('/{site}/member/subscription-plans/{slug}', [MemberSubscriptionPlansController::class, 'show']);;
$router->post('/{site}/member/subscription-plans/{slug}/subscribe', [MemberSubscriptionPlansController::class, 'subscribe']);;

$router->get('/{site}/member/payment-methods', [MemberPaymentMethodsController::class, 'index']);
$router->post('/{site}/member/payment-methods', [MemberPaymentMethodsController::class, 'store']);;
$router->post('/{site}/member/payment-methods/{paymentMethodId}/set-default', [MemberPaymentMethodsController::class, 'setDefault']);;
$router->delete('/{site}/member/payment-methods/{paymentMethodId}', [MemberPaymentMethodsController::class, 'destroy']);
$router->post('/{site}/member/payment-methods/{id}/update', [MemberPaymentMethodsController::class, 'update']);

$router->get('/{site}/member/subscription-payments', [MemberSubscriptionPaymentsController::class, 'index']);

$router->post('/{site}/member/newsletters/bulk-subscribe', [MemberNewslettersController::class, 'bulkSubscribe']);;

$router->get('/{site}/admin/subscription-plans', [AdminSubscriptionPlansController::class, 'index']);
$router->get('/{site}/admin/subscription-plans/create', [AdminSubscriptionPlansController::class, 'create']);
$router->post('/{site}/admin/subscription-plans', [AdminSubscriptionPlansController::class, 'store']);;
$router->get('/{site}/admin/subscription-plans/{id}/edit', [AdminSubscriptionPlansController::class, 'edit']);
$router->put('/{site}/admin/subscription-plans/{id}', [AdminSubscriptionPlansController::class, 'update']);
$router->delete('/{site}/admin/subscription-plans/{id}', [AdminSubscriptionPlansController::class, 'destroy']);
$router->post('/{site}/admin/subscription-plans/{id}/toggle-active', [AdminSubscriptionPlansController::class, 'toggleActive']);
$router->post('/{site}/admin/subscription-plans/{id}/toggle-featured', [AdminSubscriptionPlansController::class, 'toggleFeatured']);
$router->post('/{site}/member/subscription-plans/{slug}/validate-voucher', [MemberSubscriptionPlansController::class, 'validateVoucher']);
$router->post('/{site}/api/subscription-modal/mark-shown', [SubscriptionModalController::class, 'markShown']);

$router->post('/api/{site}/subscription-plans/{slug}/validate-voucher', [MemberSubscriptionPlansController::class, 'validateVoucher']);


$router->get('/{site}/newsletters', [NewsletterWebController::class, 'index']);
$router->get('/{site}/newsletters/{id}', [NewsletterWebController::class, 'show']);
$router->get('/{site}/newsletters/{id}/view', [NewsletterWebController::class, 'viewNewsletter']);
$router->get('/{site}/newsletters/archive', [NewsletterWebController::class, 'archive']);
$router->get('{site}/newsletters/{id}/download', [NewsletterWebController::class, 'downloadPdf']);
$router->get('/{site}/newsletters/archive/search', [NewsletterWebController::class, 'searchArchive']);
$router->get('/{site}/newsletters/search', [NewsletterWebController::class, 'search']);
$router->post('/{site}/member/newsletters/toggle', [NewsletterWebController::class, 'toggle']);
$router->post('/{site}/newsletters/track-view', [NewsletterWebController::class, 'trackPageView']);
$router->get('/{site}/newsletters/{newsletterId}/sends/{sendId}/analytics', [NewsletterWebController::class, 'sendAnalytics']);
$router->post('/{site}/newsletters/{id}/preview', [NewsletterController::class, 'preview']);
$router->post('/{site}/newsletters/{id}/sends/{sendId}/retry', [NewsletterController::class, 'retrySend']);
$router->get('/{site}/newsletters/{id}/sends/{sendId}/statistics', [NewsletterController::class, 'getSendStatistics']);

$router->get('/{site}/member/single-access', [SingleContentAccessController::class, 'index']);
$router->get('/{site}/member/single-access/show', [SingleContentAccessController::class, 'show']);
$router->get('/{site}/member/single-access/purchase', [SingleContentAccessController::class, 'purchase']);
$router->post('/{site}/member/single-access/purchase', [SingleContentAccessController::class, 'purchase']);
$router->post('/{site}/member/single-access/complete', [SingleContentAccessController::class, 'complete']);

$router->get('/{site}/newsletters/track-view', [NewsletterWebController::class, 'trackPageView']);
$router->get('/{site}/newsletters/{newsletterId}/sends/{sendId}/analytics', [NewsletterWebController::class, 'sendAnalytics']);


//faqs
$router->get('{site}/faqs', [FaqController::class, 'subscriptions']);

// Member Newsletters Routes
$router->get('/{site}/member/newsletters', [MemberNewslettersController::class, 'index']);
$router->post('/{site}/member/newsletters/unsubscribe', [MemberNewslettersController::class, 'unsubscribe']);
$router->post('/{site}/member/newsletter/signup', [MemberNewslettersController::class, 'subscribe']);

$router->get('/{site}/member/support', [MemberSupportController::class, 'index']);
$router->post('/{site}/member/support/submit', [MemberSupportController::class, 'submit']);

// Invoice routes
$router->get('/{site}/member/invoices/{paymentId}/download', [MemberInvoiceController::class, 'download']);

// Member Comments Routes
$router->get('/{site}/member/comments', [MemberCommentsController::class, 'index']);
$router->delete('/{site}/member/comments/{id}', [MemberCommentsController::class, 'destroy']);

$router->get('/{site}/member/settings', [MemberAuthController::class, 'showChangePasswordForm']);

$router->get('/{siteName}/deals', [DealsController::class, 'index']);

$router->get('/{site}/member/wishlist', [MemberWishlistController::class, 'index']);

$router->get('/api/{siteName}/product-list/{id}/details', [ProductListController::class, 'getProductDetails']);

$router->post('/{site}/default/newsletter/signup', NewsletterController::class, 'signup');

$router->get('/{site}/subscriptions/onetime', [OneTimeSubscriptionsController::class, 'index']);
$router->get('/subscriptions/onetime/{id}', [OneTimeSubscriptionsController::class, 'show']);

$router->post('/api/{site}/cart/subscription', [CartController::class, 'addSubscription']);
$router->post('/api/{site}/subscriptions/onetime/checkout', [OneTimeSubscriptionsController::class, 'checkout']);
$router->post('/api/{site}/subscriptions/onetime/confirm-payment', [OneTimeSubscriptionsController::class, 'confirmPayment']);
$router->post('/api/{site}/checkout/confirm-payment', [PaymentController::class, 'confirmPayment']);

$router->get('/api/{site}/compare', [ProductComparisonController::class, 'compare']);
$router->get('/{site}/compare', [ProductComparisonController::class, 'index']);

$router->get('/{site}/products/{id}/modal', [ProductListController::class, 'getProductModal']);

// For deals page
$router->get('/{site}/deals/{id}/modal', [DealsController::class, 'getProductModal']);


// Apply page member access check to content routes
//$router->get('{slug}', [ContentController::class, 'show'])
//    ->middleware([CheckPageMemberAccess::class]);

$router->get('/{siteName}/{regionSlug}/{pageSlug}', [RegionContentController::class, 'show'])
    //->middleware([CheckPageMemberAccess::class])
    ->where('regionSlug', 'asia-pacific|europe|americas');
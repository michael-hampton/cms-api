<?php
// API routes (return arrays -> converted to JSON)
use App\Controllers\AuthController;
use App\Controllers\Billing\OrderController;
use App\Controllers\Billing\PaymentController;
use App\Controllers\Billing\PaymentMethodController;
use App\Controllers\Billing\RefundController;
use App\Controllers\Boost\BoostController;
use App\Controllers\Cms\AuthorController;
use App\Controllers\Cms\BlockController;
use App\Controllers\Cms\BrandController;
use App\Controllers\Cms\BriefAssignmentRequestController;
use App\Controllers\Cms\BriefController;
use App\Controllers\Cms\Briefs\BriefPresetController;
use App\Controllers\Cms\CampaignController;
use App\Controllers\Cms\CategoryController;
use App\Controllers\Cms\CustomFieldDefinitionController;
use App\Controllers\Cms\ImageController;
use App\Controllers\Cms\MenuItemController;
use App\Controllers\Cms\PageController;
use App\Controllers\Cms\PageGridController;
use App\Controllers\Cms\PageHistoryController;
use App\Controllers\Cms\PipelineController;
use App\Controllers\Cms\PreviewController;
use App\Controllers\Cms\RegionSetController;
use App\Controllers\Cms\SearchController;
use App\Controllers\Cms\TagController;
use App\Controllers\Cms\TerritoryController;
use App\Controllers\Cms\UserController;
use App\Controllers\Cms\VideoController;
use App\Controllers\Crm\CrmAddressController;
use App\Controllers\Crm\CrmAttachmentController;
use App\Controllers\Crm\CrmChargingController;
use App\Controllers\Crm\CrmCommunicationsController;
use App\Controllers\Crm\CrmCountryController;
use App\Controllers\Crm\CrmDuplicateController;
use App\Controllers\Crm\CrmIssueResolutionController;
use App\Controllers\Crm\CrmManualPaymentController;
use App\Controllers\Crm\CrmMemberConsentController;
use App\Controllers\Crm\CrmMemberController;
use App\Controllers\Crm\CrmMemberPaymentMethodsController;
use App\Controllers\Crm\CrmOrderController;
use App\Controllers\Crm\CrmPaymentRefundPreviewController;
use App\Controllers\Crm\CrmSubscriptionController;
use App\Controllers\Crm\CrmSubscriptionOfferController;
use App\Controllers\Crm\CrmSubscriptionRetentionController;
use App\Controllers\Crm\RtbfController;
use App\Controllers\Crm\SarExportController;
use App\Controllers\Crm\StripeConfigController;
use App\Controllers\Front\CommentController;
use App\Controllers\Front\EstateWebsiteController;
use App\Controllers\Front\PageLikeController;
use App\Controllers\Front\WishlistController;
use App\Controllers\Internal\PermissionCacheController;
use App\Controllers\MemberAuthController;
use App\Controllers\MemberController;
use App\Controllers\MemberInsights\CampaignAnalyticsApiController;
use App\Controllers\Members\Api\BadgeAdminApiController;
use App\Controllers\Members\Api\ConsentTypeAdminApiController;
use App\Controllers\Members\Api\MemberActivityApiController;
use App\Controllers\Members\Api\MemberAddressApiController;
use App\Controllers\Members\Api\MemberApiController;
use App\Controllers\Members\Api\MemberCommentsApiController;
use App\Controllers\Members\Api\MemberConsentApiController;
use App\Controllers\Members\Api\MemberDashboardApiController;
use App\Controllers\Members\Api\MemberGiftedArticlesApiController;
use App\Controllers\Members\Api\MemberLikedPagesApiController;
use App\Controllers\Members\Api\MemberNewslettersApiController;
use App\Controllers\Members\Api\MemberOrdersApiController;
use App\Controllers\Members\Api\MemberReadingHistoryApiController;
use App\Controllers\Members\Api\MemberRewardsApiController;
use App\Controllers\Members\Api\MemberStatsApiController;
use App\Controllers\Members\Api\NewsletterRecommendationsController;
use App\Controllers\Members\Api\PlanSegmentApiController;
use App\Controllers\Members\Api\SegmentAdminApiController;
use App\Controllers\Members\Api\SegmentFieldsApiController;
use App\Controllers\Members\Api\SegmentPreviewApiController;
use App\Controllers\Members\Api\Subscriptions\MemberSubscriptionPaymentsApiController;
use App\Controllers\Members\Api\Subscriptions\MemberSubscriptionPlansApiController;
use App\Controllers\Members\Api\Subscriptions\MemberSubscriptionsApiController;
use App\Controllers\Members\Api\SubscriptionSegmentApiController;
use App\Controllers\Members\Api\SubscriptionSegmentOverrideApiController;
use App\Controllers\Members\Api\SubscriptionSegmentsApiController;
use App\Controllers\Members\MemberBadgeController;
use App\Controllers\Members\MemberPaymentMethodsController;
use App\Controllers\Members\Subscriptions\AdminSubscriptionPremiumAccessController;
use App\Controllers\MenuController;
use App\Controllers\Newsletter\EmailTemplateController;
use App\Controllers\Newsletter\EmailThemeController;
use App\Controllers\Newsletter\NewsletterBrandingController;
use App\Controllers\Newsletter\NewsletterController;
use App\Controllers\Newsletter\NewsletterIssueController;
use App\Controllers\Newsletter\NewsletterLayoutController;
use App\Controllers\Newsletter\NewsletterScheduleController;
use App\Controllers\Offers\DealsController;
use App\Controllers\Offers\OfferStatisticsDetailController;
use App\Controllers\Offers\ProductOfferBundleController;
use App\Controllers\Offers\ProductOfferController;
use App\Controllers\OpenCollab\ActivityFeedController;
use App\Controllers\OpenCollab\Admin\AdminContractController;
use App\Controllers\OpenCollab\Admin\AdminContractTemplateController;
use App\Controllers\OpenCollab\Admin\AdminContributorController;
use App\Controllers\OpenCollab\Admin\AdminGuidelinesController;
use App\Controllers\OpenCollab\Admin\AdminGuidelineTemplateController;
use App\Controllers\OpenCollab\Admin\AdminPaymentTermsController;
use App\Controllers\OpenCollab\Admin\AdminTermsController;
use App\Controllers\OpenCollab\Admin\AdminTermsEvidenceController;
use App\Controllers\OpenCollab\Admin\ModerationEscalationController;
use App\Controllers\OpenCollab\Admin\ModerationQueueController;
use App\Controllers\OpenCollab\Admin\ModerationRiskController;
use App\Controllers\OpenCollab\Admin\RbacAdminController;
use App\Controllers\OpenCollab\Admin\SiteSettingsController;
use App\Controllers\OpenCollab\ArticleApprovalController;
use App\Controllers\OpenCollab\ArticleCommentController;
use App\Controllers\OpenCollab\ArticleHistoryController;
use App\Controllers\OpenCollab\ArticlePaymentController;
use App\Controllers\OpenCollab\ContributorAuthController;
use App\Controllers\OpenCollab\ContributorBriefController;
use App\Controllers\OpenCollab\ContributorBriefInboxController;
use App\Controllers\OpenCollab\ContributorDashboardController;
use App\Controllers\OpenCollab\ContributorNotificationPreferenceController;
use App\Controllers\OpenCollab\ContributorPageController;
use App\Controllers\OpenCollab\ContributorProfileSampleLinksController;
use App\Controllers\OpenCollab\ContributorRequestController;
use App\Controllers\OpenCollab\ContributorSettingsController;
use App\Controllers\OpenCollab\DashboardPageNewController;
use App\Controllers\OpenCollab\EarningsDisputeController;
use App\Controllers\OpenCollab\ImageLibraryController;
use App\Controllers\OpenCollab\InvitationController;
use App\Controllers\OpenCollab\NotificationController;
use App\Controllers\OpenCollab\OnboardingController;
use App\Controllers\OpenCollab\OnboardingDashboardController;
use App\Controllers\OpenCollab\OpenCollabDocumentController;
use App\Controllers\OpenCollab\PaymentRetryController;
use App\Controllers\OpenCollab\PayoutController;
use App\Controllers\OpenCollab\PayoutStatementController;
use App\Controllers\OpenCollab\ResendInvitationController;
use App\Controllers\OpenCollab\StripeConnectController;
use App\Controllers\OpenCollab\StripeWebhookController;
use App\Controllers\OpenCollab\TermsOnboardingController;
use App\Controllers\OpenCollab\ViolationController;
use App\Controllers\OpenCollab\WidgetSettingsController;
use App\Controllers\Product\MerchantContactController;
use App\Controllers\Product\MerchantController;
use App\Controllers\Product\MerchantImportController;
use App\Controllers\Product\MerchantProductFeedController;
use App\Controllers\Product\ProductController;
use App\Controllers\Product\ProductMatchingController;
use App\Controllers\Product\ReviewController;
use App\Controllers\Product\VariantController;
use App\Controllers\Recommendations\RecommendationController;
use App\Controllers\Rewards\RewardAuditLogController;
use App\Controllers\Rewards\RewardDefinitionsAdminController;
use App\Controllers\Rewards\RewardsAdminController;
use App\Controllers\Shopping\AddressController;
use App\Controllers\Shopping\CartController;
use App\Controllers\Shopping\GiftPromotionController;
use App\Controllers\Shopping\ProductListController;
use App\Controllers\SiteController;
use App\Controllers\Subscription\IssueDeliveryController;
use App\Controllers\Subscription\PrintFulfillmentController;
use App\Controllers\Subscription\PrintRunController;
use App\Controllers\Subscription\SubscriptionCommunicationController;
use App\Controllers\Subscription\SubscriptionCommunicationHistoryController;
use App\Controllers\Subscription\SubscriptionCommunicationTrackingController;
use App\Controllers\Subscription\SubscriptionController;
use App\Controllers\Subscription\SubscriptionModalController;
use App\Controllers\Subscription\ShopAccountApiController;
use App\Controllers\Vouchers\SubscriptionVoucherController;
use App\Framework\Middleware\EnsureOnboardingNotExpired;
use App\Middleware\OpenCollab\OnboardingRouteGuard;
use App\Controllers\Subscription\SubscriptionPlanPricingController;
use App\Controllers\Subscription\SubscriptionPlanSubscriberController;
use App\Controllers\Subscription\WorkflowRunController;
use App\Controllers\Vouchers\VoucherController;
use App\Controllers\WorkflowController;
use App\Framework\Authorization\AuthenticateWithToken;
use App\Framework\Http\Router;
use App\Framework\Middleware\AuthenticateMemberWithToken;
use App\Framework\Middleware\RequireBriefAssignmentAccess;
use App\Framework\Middleware\VerifyCsrfToken;

/**
 * @var $router Router
 */
$router->post('/api/auth/login', [AuthController::class, 'globalLogin']);
$router->get('/api/boosts', [BoostController::class, 'index']);
$router->get('/api/boosts/{id}', [BoostController::class, 'show']);
$router->post('/api/boosts', [BoostController::class, 'store']);
$router->post('/api/boosts/{id}/activate', [BoostController::class, 'activate']);
$router->post('/api/boosts/{id}/expire', [BoostController::class, 'expire']);
$router->post('/api/boosts/{id}/cancel', [BoostController::class, 'cancel']);
$router->post('/api/boosts/{id}/pause', [BoostController::class, 'pause']);
$router->post('/api/boosts/{id}/resume', [BoostController::class, 'resume']);
$router->get('/api/boosts/{id}/stats', [BoostController::class, 'stats']);
$router->get('/api/merchants/{merchantId}/boost-stats', [BoostController::class, 'merchantStats']);
$router->get('/api/merchants/{merchantId}/boost-suggestions', [BoostController::class, 'suggestions']);
$router->get('/api/merchants/{id}/auto-boost/preview', [BoostController::class, 'autoBoostPreview']);
$router->post('/api/merchants/{merchantId}/auto-boost/settings', [BoostController::class, 'saveAutoBoostSettings']);
$router->get('/merchant-portal/boost', [BoostController::class, 'boostPage']);
$router->get('/api/merchants/{merchantId}/auto-boost/settings', [BoostController::class, 'getAutoBoostSettings']);
$router->get('/api/merchants/{merchantId}/products/search', [BoostController::class, 'searchMerchantProducts']);
$router->get('/api/merchants/{merchantId}/offers/search', [BoostController::class, 'searchMerchantOffers']);
$router->get('/boosts/aggregate', [BoostController::class, 'aggregateStats']);
$router->post('/api/{site}/boost/click', [BoostController::class, 'recordClick']);
$router->post('/api/{site}/internal/workflow/run', [WorkflowController::class, 'run']);
$router->get('/api/{site}/internal/workflow/logs', [WorkflowController::class, 'logs']);
$router->get('/api/{site}/internal/workflow/classes', [WorkflowController::class, 'classes']);
$router->get('/api/{site}/internal/workflow/listen', [WorkflowController::class, 'listen']);
$router->post('/internal/permissions/cache/invalidate', [PermissionCacheController::class, 'invalidate']);

$router->put('/api/sites/{id}/toggle-status', [SiteController::class, 'toggleStatus']);


$router->post('/api/{site}/member/auth/login', [MemberAuthController::class, 'apiLogin']);

$router->get('/api/{site}/admin/badges', [BadgeAdminApiController::class, 'index']);
$router->get('/api/{site}/admin/badges/{id}', [BadgeAdminApiController::class, 'show']);
$router->post('/api/{site}/admin/badges', [BadgeAdminApiController::class, 'store']);
$router->put('/api/{site}/admin/badges/{id}', [BadgeAdminApiController::class, 'update']);
$router->delete('/api/{site}/admin/badges/{id}', [BadgeAdminApiController::class, 'destroy']);

$router->get('/api/{site}/admin/consent-types', [ConsentTypeAdminApiController::class, 'index']);
$router->get('/api/{site}/admin/consent-types/{id}', [ConsentTypeAdminApiController::class, 'show']);
$router->post('/api/{site}/admin/consent-types', [ConsentTypeAdminApiController::class, 'store']);
$router->put('/api/{site}/admin/consent-types/{id}', [ConsentTypeAdminApiController::class, 'update']);
$router->delete('/api/{site}/admin/consent-types/{id}', [ConsentTypeAdminApiController::class, 'destroy']);

$router->get('/api/{site}/admin/segments', [SegmentAdminApiController::class, 'index']);
$router->get('/api/{site}/admin/segments/{id}', [SegmentAdminApiController::class, 'show']);
$router->post('/api/{site}/admin/segments', [SegmentAdminApiController::class, 'store']);
$router->put('/api/{site}/admin/segments/{id}', [SegmentAdminApiController::class, 'update']);
$router->delete('/api/{site}/admin/segments/{id}', [SegmentAdminApiController::class, 'destroy']);


$router->group(['prefix' => 'api/{site}/member', 'middleware' => [AuthenticateMemberWithToken::class]], function ($router) {
    $router->post('/account/subscriptions/{id}/cancel', [ShopAccountApiController::class, 'cancelSubscription'], middleware: [VerifyCsrfToken::class]);
    $router->post('/account/subscriptions/{id}/reactivate', [ShopAccountApiController::class, 'reactivateSubscription'], middleware: [VerifyCsrfToken::class]);
    $router->post('/account/subscriptions/{id}/pause', [ShopAccountApiController::class, 'pauseSubscription'], middleware: [VerifyCsrfToken::class]);
    $router->post('/account/subscriptions/{id}/resume', [ShopAccountApiController::class, 'resumeSubscription'], middleware: [VerifyCsrfToken::class]);
    $router->get('/account/subscriptions/{id}/settle-payment', [ShopAccountApiController::class, 'settlePayment']);
    $router->get('/account/billing/payment-methods', [ShopAccountApiController::class, 'paymentMethods']);
    $router->post('/account/billing/setup-intent', [ShopAccountApiController::class, 'createSetupIntent'], middleware: [VerifyCsrfToken::class]);
    $router->post('/account/billing/finalise-setup-intent', [ShopAccountApiController::class, 'finaliseSetupIntent'], middleware: [VerifyCsrfToken::class]);
    $router->post('/account/billing/set-default', [ShopAccountApiController::class, 'setDefaultCard'], middleware: [VerifyCsrfToken::class]);
    $router->post('/account/billing/remove-card', [ShopAccountApiController::class, 'removeCard'], middleware: [VerifyCsrfToken::class]);

    $router->get('/dashboard', [MemberDashboardApiController::class, 'index']);
    $router->get('/dashboard/overview', [MemberDashboardApiController::class, 'overview']);
    $router->get('/dashboard/activity', [MemberDashboardApiController::class, 'activity']);
    $router->get('/dashboard/discovery', [MemberDashboardApiController::class, 'discovery']);
    $router->get('/dashboard/newsletters', [MemberDashboardApiController::class, 'newsletters']);
    $router->get('/dashboard/rewards', [MemberDashboardApiController::class, 'rewards']);
    $router->get('/dashboard/subscriptions', [MemberDashboardApiController::class, 'subscriptions']);
    $router->get('/dashboard/stats', [MemberStatsApiController::class, 'stats']);

    // Member Activity & Badges API
    $router->get('/activity', [MemberActivityApiController::class, 'index']);

    $router->get('/gifted-articles', [MemberGiftedArticlesApiController::class, 'index']);

// GET  /api/{site}/member/gift-modal/{pageSlug}    → page info + allowance for gift modal
    $router->get('/gift-modal/{pageSlug}', [MemberGiftedArticlesApiController::class, 'modal']);

// POST /api/{site}/gift-article/{pageSlug}         → send a gift
    $router->post('/gift-article/{pageSlug}', [MemberGiftedArticlesApiController::class, 'gift']);

// POST /api/{site}/gift/{token}/claim              → claim a gift
    $router->post('/gift/{token}/claim', [MemberGiftedArticlesApiController::class, 'claim']);


// Member Badge notification API (existing MemberBadgeController, already API-shaped)
    $router->get('/badges', [MemberActivityApiController::class, 'badges']);
    $router->get('/badges/new', [MemberBadgeController::class, 'getNewBadges']);
    $router->post('/badges/mark-shown', [MemberBadgeController::class, 'markBadgeShown']);
    $router->post('/badges/mark-as-shown', [MemberBadgeController::class, 'markAsShown']);

    $router->get('/addresses/search', [MemberAddressApiController::class, 'index']);
    $router->get('/addresses', [MemberAddressApiController::class, 'show']);
    $router->post('/addresses', [MemberAddressApiController::class, 'store']);
    $router->delete('/addresses/{id}', [MemberAddressApiController::class, 'destroy']);
    $router->post('/addresses/{id}', [MemberAddressApiController::class, 'update']); // If you don't support PUT
    $router->put('/addresses/{id}', [MemberAddressApiController::class, 'update']);
    $router->post('/addresses/{id}/delete', [MemberAddressApiController::class, 'destroy']);
    $router->post('/addresses/{id}/set-default', [MemberAddressApiController::class, 'setDefault']);

// Member Comments API
    $router->get('/comments', [MemberCommentsApiController::class, 'index']);
    $router->delete('/comments/{commentId}', [MemberCommentsApiController::class, 'destroy']);

    $router->get('/account-details', [MemberApiController::class, 'me']);
    $router->post('/account-details', [MemberApiController::class, 'updateAccountDetails']);
    $router->post('/settings/privacy', [MemberApiController::class, 'updatePrivacy']);
    $router->post('/settings/communication-preferences', [MemberApiController::class, 'updateCommunicationPreferences']);
    $router->post('/consent/update', [MemberConsentApiController::class, 'update']);
    $router->get('/consent/audit-history', [MemberConsentApiController::class, 'auditHistory']);
    $router->get('/consent', [MemberConsentApiController::class, 'index']);
    $router->get('/consent/types', [MemberConsentApiController::class, 'getConsentTypes']);

// Member Liked Pages API
    $router->get('/liked-pages', [MemberLikedPagesApiController::class, 'index']);
    $router->post('/pages/like/{pageId}', [PageLikeController::class, 'toggle']);

    $router->get('/wishlist', 'App\Controllers\Members\Api\MemberWishlistApiController@index');
    $router->delete('/wishlist/{id}', 'App\Controllers\Members\Api\MemberWishlistApiController@remove');

    $router->get('/rewards', [MemberRewardsApiController::class, 'rewards']);
    $router->post('/rewards/{rewardId}/claim', [MemberRewardsApiController::class, 'claim']);


    $router->post('/rewards/{rewardId}/track/{action}', [MemberRewardsApiController::class, 'trackClick']);


// Orders API
    $router->get('/orders', [MemberOrdersApiController::class, 'index']);
    $router->get('/orders/{id}', [MemberOrdersApiController::class, 'show']);
    $router->post('/orders/{orderId}/cancel', [MemberOrdersApiController::class, 'cancel']);
    $router->post('/orders/{id}/refund', [OrderController::class, 'refund']);

    $router->get('/newsletters', [MemberNewslettersApiController::class, 'index']);
    $router->post('/newsletters/unsubscribe', [MemberNewslettersApiController::class, 'unsubscribe']);
    $router->post('/newsletter/signup', [MemberNewslettersApiController::class, 'subscribe']);
    $router->get('/newsletters/recommendations', [NewsletterRecommendationsController::class, '__invoke']);
    $router->post('/newsletters/bulk-subscribe', [MemberNewslettersApiController::class, 'bulkSubscribe']);
// In the member newsletters section
    $router->post('/newsletters/upgrade-options', [MemberNewslettersApiController::class, 'getUpgradeOptions']);
    $router->post('/newsletters/process-upgrade', [MemberNewslettersApiController::class, 'processUpgrade']);


// Member Reading History API
    $router->get('/reading-history', [MemberReadingHistoryApiController::class, 'index']);

    $router->get('/subscription-plans',
        [MemberSubscriptionPlansApiController::class, 'index']);

    $router->get('/subscription-plans/{slug}',
        [MemberSubscriptionPlansApiController::class, 'show']);

    $router->post('/subscription-plans/{slug}/subscribe',
        [MemberSubscriptionPlansApiController::class, 'subscribe']);

    $router->post('/subscription-plans/{slug}/validate-voucher',
        [MemberSubscriptionPlansApiController::class, 'validateVoucher']);

    $router->get('/subscriptions/overview',
        [MemberSubscriptionsApiController::class, 'overview']);

    $router->get('/subscriptions/{subscriptionId}/history',
        [MemberSubscriptionsApiController::class, 'history']);

    $router->post('/subscriptions/{subscriptionId}/cancel',
        [MemberSubscriptionsApiController::class, 'cancel']);

    $router->post('/subscriptions/{subscriptionId}/reactivate',
        [MemberSubscriptionsApiController::class, 'reactivate']);

    $router->post('/subscriptions/{subscriptionId}/auto-renew',
        [MemberSubscriptionsApiController::class, 'autoRenew']);

    $router->post('/subscriptions/{subscriptionId}/pause-delivery',
        [MemberSubscriptionsApiController::class, 'pauseDelivery']);

    $router->post('/subscriptions/{subscriptionId}/resume-delivery',
        [MemberSubscriptionsApiController::class, 'resumeDelivery']);

    $router->post('/subscriptions/{subscriptionId}/update-billing-date',
        [MemberSubscriptionsApiController::class, 'updateBillingDate']);

    $router->post('/subscriptions/{subscriptionId}/preview-billing-change',
        [MemberSubscriptionsApiController::class, 'previewBillingDateChange']);

// ----- Subscription Payments -----
    $router->get('/subscription-payments',
        [MemberSubscriptionPaymentsApiController::class, 'index']);
});

// Guest: accept an invitation and register
$router->post(
    '/api/{site}/open-collab/invitations/{token}/accept',
    [InvitationController::class, 'accept']
);

// Stripe webhook — must NOT be behind auth middleware
$router->post(
    '/api/{site}/open-collab/stripe/webhook',
    [StripeWebhookController::class, 'handle']
);

$router->get('/api/{site}/open-collab/onboarding-status', [OnboardingDashboardController::class, 'status']);


$router->group(['prefix' => 'api', 'middleware' => AuthenticateWithToken::class], function ($router) {

    // Pages API
    $router->group(['prefix' => '{site}'], function ($router) {
        $router->get('/auth/me', AuthController::class, 'me');

        $router->get('/contact-info', SiteController::class, 'getContactInfo');

        $router->post(
            '/open-collab/invitations',
            [InvitationController::class, 'store']
        );

        $router->post(
            '/open-collab/contributor/expertise',
            [ContributorSettingsController::class, 'saveExpertise']
        );

        $router->get('/open-collab/dashboard/widgets/{slug}', [DashboardPageNewController::class, 'getWidget']);
        $router->get('/open-collab/dashboard/widgets', [DashboardPageNewController::class, 'index']);
        $router->put('/open-collab/dashboard/widgets/positions', [WidgetSettingsController::class, 'updatePositions']);
        $router->put('/open-collab/dashboard/widgets/{key}/settings', [WidgetSettingsController::class, 'saveWidgetConfig']);

        $router->post(
            '/open-collab/contributor/avatar',
            [ContributorSettingsController::class, 'uploadAvatar']
        );

        $router->delete(
            '/open-collab/contributor/avatar',
            [ContributorSettingsController::class, 'removeAvatar']
        );

        $router->put(
            '/open-collab/contributor',
            [ContributorSettingsController::class, 'updateProfile']
        );

        $router->put(
            '/open-collab/profile/sample-links',
            [ContributorProfileSampleLinksController::class, 'update']
        );

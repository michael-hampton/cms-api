<?php
// API routes (return arrays -> converted to JSON)
use App\Controllers\AuthController;
use App\Controllers\Billing\OrderController;
use App\Controllers\Billing\PaymentController;
use App\Controllers\Billing\PaymentMethodController;
use App\Controllers\Billing\RefundController;
use App\Controllers\Billing\SavedPaymentMethodsController;
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
use App\Controllers\Crm\CrmMemberNoteController;
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
use App\Controllers\OpenCollab\Admin\AdminPayoutController;
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
use App\Controllers\Subscription\BusinessDecisionAdminController;
use App\Controllers\Subscription\CancellationReasonAdminController;
use App\Controllers\Subscription\RefundReasonAdminController;
use App\Controllers\Subscription\SuspensionReasonAdminController;
use App\Controllers\Subscription\IssueDeliveryController;
use App\Controllers\Subscription\LabelRunReportController;
use App\Controllers\Subscription\AdHocFulfilmentController;
use App\Controllers\Subscription\PrintBatchReportController;
use App\Controllers\Subscription\PrintFulfillmentController;
use App\Controllers\Subscription\PrintRunController;
use App\Controllers\Subscription\PrintVendorConnectionController;
use App\Controllers\Subscription\ShopAccountApiController;
use App\Controllers\Subscription\SubscriptionCommunicationController;
use App\Controllers\Subscription\SubscriptionCommunicationHistoryController;
use App\Controllers\Subscription\SubscriptionCommunicationLetterCodeController;
use App\Controllers\Subscription\SubscriptionCommunicationScopeController;
use App\Controllers\Subscription\SubscriptionCommunicationTrackingController;
use App\Controllers\Subscription\SubscriptionController;
use App\Controllers\Subscription\SubscriptionModalController;
use App\Controllers\Subscription\SubscriptionPlanPricingController;
use App\Controllers\Subscription\SubscriptionPlanSubscriberController;
use App\Controllers\Subscription\SubscriptionPolicyOverrideController;
use App\Controllers\Subscription\WorkflowRunController;
use App\Controllers\Vouchers\SubscriptionVoucherController;
use App\Controllers\Vouchers\VoucherController;
use App\Controllers\WorkflowController;
use App\Framework\Authorization\AuthenticateWithToken;
use App\Framework\Http\Router;
use App\Framework\Middleware\AuthenticateMemberWithToken;
use App\Framework\Middleware\EnsureOnboardingNotExpired;
use App\Framework\Middleware\RequireBriefAssignmentAccess;
use App\Framework\Middleware\VerifyCsrfToken;
use App\Middleware\OpenCollab\OnboardingRouteGuard;

// no longer routed - see SavedPaymentMethodsController

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
$router->get('/api/{site}/internal/workflow/history', [WorkflowController::class, 'history']);
$router->get('/api/{site}/internal/workflow/count', [WorkflowController::class, 'count']);
$router->post('/api/{site}/internal/workflow/{id}/cancel', [WorkflowController::class, 'cancel']);
$router->post('/api/{site}/internal/workflow/{id}/terminate', [WorkflowController::class, 'terminate']);
$router->post('/api/{site}/internal/workflow/{id}/reset', [WorkflowController::class, 'reset']);


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

$router->get('/api/{site}/admin/cancellation-reasons', [CancellationReasonAdminController::class, 'index']);
$router->get('/api/{site}/admin/cancellation-reasons/{id}', [CancellationReasonAdminController::class, 'show']);
$router->post('/api/{site}/admin/cancellation-reasons', [CancellationReasonAdminController::class, 'store']);
$router->put('/api/{site}/admin/cancellation-reasons/{id}', [CancellationReasonAdminController::class, 'update']);
$router->delete('/api/{site}/admin/cancellation-reasons/{id}', [CancellationReasonAdminController::class, 'destroy']);

$router->get('/api/{site}/admin/refund-reasons', [RefundReasonAdminController::class, 'index']);
$router->get('/api/{site}/admin/refund-reasons/{id}', [RefundReasonAdminController::class, 'show']);
$router->post('/api/{site}/admin/refund-reasons', [RefundReasonAdminController::class, 'store']);
$router->put('/api/{site}/admin/refund-reasons/{id}', [RefundReasonAdminController::class, 'update']);
$router->delete('/api/{site}/admin/refund-reasons/{id}', [RefundReasonAdminController::class, 'destroy']);

$router->get('/api/{site}/admin/suspension-reasons', [SuspensionReasonAdminController::class, 'index']);
$router->get('/api/{site}/admin/suspension-reasons/{id}', [SuspensionReasonAdminController::class, 'show']);
$router->post('/api/{site}/admin/suspension-reasons', [SuspensionReasonAdminController::class, 'store']);
$router->put('/api/{site}/admin/suspension-reasons/{id}', [SuspensionReasonAdminController::class, 'update']);
$router->delete('/api/{site}/admin/suspension-reasons/{id}', [SuspensionReasonAdminController::class, 'destroy']);

$router->get('/api/{site}/admin/business-decisions', [BusinessDecisionAdminController::class, 'index']);
$router->get('/api/{site}/admin/business-decisions/{id}', [BusinessDecisionAdminController::class, 'show']);
$router->post('/api/{site}/admin/business-decisions', [BusinessDecisionAdminController::class, 'store']);
$router->put('/api/{site}/admin/business-decisions/{id}', [BusinessDecisionAdminController::class, 'update']);
$router->post('/api/{site}/admin/business-decisions/assign', [BusinessDecisionAdminController::class, 'assign']);
$router->put('/api/{site}/admin/business-decisions/{id}/reason-policies', [BusinessDecisionAdminController::class, 'upsertReasonPolicy']);
$router->get('/api/{site}/admin/business-decisions/{id}/reason-policies', [BusinessDecisionAdminController::class, 'listReasonPolicies']);
$router->put('/api/{site}/admin/business-decisions/{id}/refund-reason-policies', [BusinessDecisionAdminController::class, 'upsertRefundReasonPolicy']);
$router->get('/api/{site}/admin/business-decisions/{id}/refund-reason-policies', [BusinessDecisionAdminController::class, 'listRefundReasonPolicies']);
$router->put('/api/{site}/admin/business-decisions/{id}/suspension-policy', [BusinessDecisionAdminController::class, 'upsertSuspensionPolicy']);
$router->get('/api/{site}/admin/business-decisions/{id}/suspension-policy', [BusinessDecisionAdminController::class, 'getSuspensionPolicy']);

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
    $router->get('/account/billing/payment-methods', [SavedPaymentMethodsController::class, 'list']);
    $router->post('/account/billing/setup-intent', [SavedPaymentMethodsController::class, 'createSetupIntent'], middleware: [VerifyCsrfToken::class]);
    $router->post('/account/billing/finalise-setup-intent', [SavedPaymentMethodsController::class, 'store'], middleware: [VerifyCsrfToken::class]);
    $router->post('/account/billing/set-default', [SavedPaymentMethodsController::class, 'setDefault'], middleware: [VerifyCsrfToken::class]);
    $router->post('/account/billing/remove-card', [SavedPaymentMethodsController::class, 'destroy'], middleware: [VerifyCsrfToken::class]);

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

        $router->post(
            '/open-collab/admin/contributors/{id}/tax',
            [AdminContributorController::class, 'updateTax']
        );

        $router->post(
            '/open-collab/profile/sample-links',
            [ContributorProfileSampleLinksController::class, 'update']
        );

        $router->get('/open-collab/onboarding/terms', [TermsOnboardingController::class, 'show']);
        $router->post('/open-collab/onboarding/terms', [TermsOnboardingController::class, 'accept']);

        $router->get(
            '/open-collab/dashboard',
            [ContributorDashboardController::class, 'show']
        );

        $router->get(
            '/open-collab/briefs',
            [ContributorBriefInboxController::class, 'apiIndex']
        );

        $router->group(['middleware' => [RequireBriefAssignmentAccess::class]], function ($router) {
            $router->get('/open-collab/briefs/{brief}', [ContributorBriefController::class, 'apiShow']);
            $router->get('/open-collab/briefs/{brief}/timeline', [ContributorBriefController::class, 'timeline']);
            $router->get('/open-collab/briefs/{brief}/tasks', [ContributorBriefController::class, 'tasks']);
            $router->patch('/open-collab/briefs/{brief}/tasks/{task}', [ContributorBriefController::class, 'updateTask']);
            $router->post('/open-collab/briefs/{brief}/tasks/{task}', [ContributorBriefController::class, 'updateTask']);
            $router->get('/open-collab/briefs/{brief}/attachments', [ContributorBriefController::class, 'attachments']);
            $router->post('/open-collab/briefs/{brief}/attachments', [ContributorBriefController::class, 'uploadAttachment']);
            $router->delete('/open-collab/briefs/{brief}/attachments/{attachment}', [ContributorBriefController::class, 'deleteAttachment']);
            $router->get('/open-collab/briefs/{brief}/comments', [ContributorBriefController::class, 'comments']);
            $router->post('/open-collab/briefs/{brief}/comments', [ContributorBriefController::class, 'createComment']);
            $router->post('/open-collab/briefs/{brief}/accept', [ContributorBriefController::class, 'accept']);
            $router->post('/open-collab/briefs/{brief}/reject', [ContributorBriefController::class, 'reject']);
            $router->post('/open-collab/briefs/{brief}/negotiate', [ContributorBriefController::class, 'negotiate']);
            $router->post('/open-collab/briefs/{brief}/request-clarification', [ContributorBriefController::class, 'requestClarification']);
            $router->post('/open-collab/briefs/{brief}/request-deadline-change', [ContributorBriefController::class, 'requestDeadlineChange']);
            $router->post('/open-collab/briefs/{brief}/submit', [ContributorBriefController::class, 'submit']);
            $router->post('/open-collab/briefs/{brief}/resubmit', [ContributorBriefController::class, 'resubmit']);
        });
        $router->patch('/open-collab/comments/{comment}', [ContributorBriefController::class, 'updateComment']);
        $router->post('/open-collab/comments/{comment}', [ContributorBriefController::class, 'updateComment']);
        $router->post('/open-collab/comments/{comment}/resolve', [ContributorBriefController::class, 'resolveComment']);

        // Contributor pages (articles)
        $router->get(
            '/open-collab/pages',
            [ContributorPageController::class, 'index']
        );

        $router->group(['middleware' => [OnboardingRouteGuard::class]], function ($router) {
            $router->post(
                '/open-collab/pages',
                [ContributorPageController::class, 'store']
            );

            $router->put(
                '/open-collab/pages/{id}',
                [ContributorPageController::class, 'update']
            );

            $router->delete(
                '/open-collab/pages/{id}',
                [ContributorPageController::class, 'destroy']
            );
        });

        $router->get(
            '/open-collab/admin/moderation',
            [ModerationQueueController::class, 'index']
        );

        $router->get('/open-collab/admin/moderation/{queueEntryId}', [ModerationQueueController::class, 'show']);
        $router->post('/open-collab/admin/articles/{id}/request-changes', [ArticleApprovalController::class, 'requestChanges']);
        $router->post('/open-collab/admin/moderation/{queueEntryId}/claim', [ModerationQueueController::class, 'claim']);
        $router->post('/open-collab/admin/moderation/{queueEntryId}/risks', [ModerationRiskController::class, 'store']);
        $router->post('/open-collab/admin/risks/{riskMarkerId}/resolve', [ModerationRiskController::class, 'resolve']);
        $router->post('/open-collab/admin/risks/{riskMarkerId}/dismiss', [ModerationRiskController::class, 'dismiss']);
        $router->post('/open-collab/admin/moderation/{queueEntryId}/release', [ModerationQueueController::class, 'release']);
        $router->get('/open-collab/admin/escalations', [ModerationEscalationController::class, 'index']);
        $router->post('/open-collab/admin/escalations/{id}/resolve', [ModerationEscalationController::class, 'resolve']);
        $router->post('/open-collab/admin/escalations/{id}/assign', [ModerationEscalationController::class, 'assign']);
        $router->post('/open-collab/admin/escalations/{id}/acknowledge', [ModerationEscalationController::class, 'acknowledge']);




        $router->post('/open-collab/admin/moderation/{queueEntryId}/escalate', [ModerationEscalationController::class, 'store']);
        $router->get('/open-collab/images', [ImageLibraryController::class, 'index']);
        $router->post('/open-collab/images', [ImageLibraryController::class, 'store']);
        $router->get('/open-collab/images/{imageId}', [ImageLibraryController::class, 'show']);

        $router->get(
            '/open-collab/admin/contributors',
            [AdminContributorController::class, 'index']
        );

        $router->post(
            '/open-collab/admin/contract-templates',
            [AdminContractController::class, '']
        );

        $router->get('/open-collab/admin/contracts', [AdminContractController::class, 'index']);
        $router->get('/open-collab/admin/contracts/latest', [AdminContractController::class, 'latest']);
        $router->post('/open-collab/admin/contracts', [AdminContractController::class, 'store']);
        $router->post('/open-collab/admin/contracts/manual', [AdminContractController::class, 'store']);
        $router->get('/open-collab/admin/contracts/{id}', [AdminContractController::class, 'show']);
        $router->put('/open-collab/admin/contracts/{id}', [AdminContractController::class, 'update']);
        $router->delete('/open-collab/admin/contracts/{id}', [AdminContractController::class, 'destroy']);
        $router->post('/open-collab/admin/contracts/{id}/publish', [AdminContractController::class, 'publish']);
        $router->post('/open-collab/admin/contracts/{id}/clone', [AdminContractController::class, 'clone']);
        $router->post('/open-collab/admin/contracts/from-template', [AdminContractController::class, 'storeFromTemplate']);
        $router->post('/open-collab/admin/contracts/from-document', [AdminContractController::class, 'storeFromDocument']);

        $router->get('/open-collab/admin/terms', [AdminTermsController::class, 'index']);
        $router->get('/open-collab/admin/terms/latest', [AdminTermsController::class, 'latest']);
        $router->post('/open-collab/admin/terms', [AdminTermsController::class, 'store']);
        $router->get('/open-collab/admin/terms/{id}', [AdminTermsController::class, 'show']);
        $router->put('/open-collab/admin/terms/{id}', [AdminTermsController::class, 'update']);
        $router->post('/open-collab/admin/terms/{id}/publish', [AdminTermsController::class, 'publish']);
        $router->post('/open-collab/admin/terms/from-document', [AdminTermsController::class, 'storeFromDocument']);
        $router->get('/open-collab/admin/terms-evidence/{id}', [AdminTermsEvidenceController::class, 'show']);
        $router->delete(
            '/open-collab/admin/terms/{id}',
            [AdminTermsController::class, 'destroy'],
        );

        $router->get('/open-collab/admin/contract-templates', [AdminContractTemplateController::class, 'index']);
        $router->post('/open-collab/admin/contract-templates', [AdminContractTemplateController::class, 'store']);
        $router->post('/open-collab/admin/contract-templates/import-document', [AdminContractTemplateController::class, 'importDocument']);
        $router->put('/open-collab/admin/contract-templates/{id}', [AdminContractTemplateController::class, 'update']);
        $router->delete('/open-collab/admin/contract-templates/{id}', [AdminContractTemplateController::class, 'destroy']);

        // Specialized Guideline Actions
        $router->get('/open-collab/admin/guidelines', [AdminGuidelinesController::class, 'index']);
        $router->get('/open-collab/admin/guidelines/latest', [AdminGuidelinesController::class, 'latest']);
        $router->post('/open-collab/admin/guidelines', [AdminGuidelinesController::class, 'store']);
        $router->post('/open-collab/admin/guidelines/manual', [AdminGuidelinesController::class, 'store']);
        $router->get('/open-collab/admin/guidelines/{id}', [AdminGuidelinesController::class, 'show']);
        $router->put('/open-collab/admin/guidelines/{id}', [AdminGuidelinesController::class, 'update']);
        $router->delete('/open-collab/admin/guidelines/{id}', [AdminGuidelinesController::class, 'destroy']);
        $router->post('/open-collab/admin/guidelines/{id}/publish', [AdminGuidelinesController::class, 'publish']);
        $router->post('/open-collab/admin/guidelines/{id}/clone', [AdminGuidelinesController::class, 'clone']);
        $router->post('/open-collab/admin/guidelines/from-template', [AdminGuidelinesController::class, 'storeFromTemplate']);
        $router->post('/open-collab/admin/guidelines/from-document', [AdminGuidelinesController::class, 'storeFromDocument']);

        // ─── Guideline Templates ──────────────────────────────────────────────
        $router->get('/open-collab/admin/guideline-templates', [AdminGuidelineTemplateController::class, 'index']);
        $router->post('/open-collab/admin/guideline-templates', [AdminGuidelineTemplateController::class, 'store']);
        $router->post('/open-collab/admin/guideline-templates/import-document', [AdminGuidelineTemplateController::class, 'importDocument']);
        $router->put('/open-collab/admin/guideline-templates/{id}', [AdminGuidelineTemplateController::class, 'update']);
        $router->delete('/open-collab/admin/guideline-templates/{id}', [AdminGuidelineTemplateController::class, 'destroy']);

        $router->get(
            '/open-collab/admin/contributors/{id}',
            [AdminContributorController::class, 'show']
        );

        $router->post(
            '/open-collab/admin/contributors/{id}/deactivate',
            [AdminContributorController::class, 'deactivate']
        );

        $router->post(
            '/open-collab/admin/contributors/{id}/reactivate',
            [AdminContributorController::class, 'reactivate']
        );

        $router->post(
            '/open-collab/admin/contributors/{id}/close',
            [AdminContributorController::class, 'close']
        );

        $router->post(
            '/open-collab/admin/contributors/{id}/grant-access',
            [AdminContributorController::class, 'grantAccess']
        );

        $router->post(
            '/open-collab/admin/contributors/{id}/revoke-access',
            [AdminContributorController::class, 'revokeAccess']
        );
        $router->post(
            '/open-collab/admin/contributors/{id}/role',
            [AdminContributorController::class, 'updateRole']
        );
        $router->get(
            '/open-collab/admin/contributors/{id}/capabilities',
            [AdminContributorController::class, 'capabilities']
        );
        $router->post(
            '/open-collab/admin/contributors/{id}/capabilities/{capabilityKey}/grant',
            [AdminContributorController::class, 'grantCapability']
        );
        $router->post(
            '/open-collab/admin/contributors/{id}/capabilities/{capabilityKey}/revoke',
            [AdminContributorController::class, 'revokeCapability']
        );
        $router->delete(
            '/open-collab/admin/contributors/{id}/capabilities/{capabilityKey}/override',
            [AdminContributorController::class, 'resetCapability']
        );

        $router->get(
            '/open-collab/admin/invitations',
            [AdminContributorController::class, 'invitations']
        );

        $router->post(
            '/open-collab/admin/invitations/{id}/resend',
            [AdminContributorController::class, 'resendInvitation']
        );

        $router->delete(
            '/open-collab/admin/invitations/{id}',
            [AdminContributorController::class, 'revokeInvitation']
        );

        $router->get('/open-collab/admin/rbac', [RbacAdminController::class, 'summary']);
        $router->get('/open-collab/admin/rbac/permissions', [RbacAdminController::class, 'permissions']);
        $router->get('/open-collab/admin/rbac/roles', [RbacAdminController::class, 'roles']);
        $router->get('/open-collab/admin/rbac/members', [RbacAdminController::class, 'members']);
        $router->get('/open-collab/admin/rbac/overrides', [RbacAdminController::class, 'overrides']);
        $router->get('/open-collab/admin/rbac/audit', [RbacAdminController::class, 'audit']);
        $router->post('/open-collab/admin/rbac/roles', [RbacAdminController::class, 'createRole']);
        $router->delete('/open-collab/admin/rbac/roles/{roleId}', [RbacAdminController::class, 'deleteRole']);
        $router->post('/open-collab/admin/rbac-role-permissions/{roleId}', [RbacAdminController::class, 'syncRolePermissions']);
        $router->post('/open-collab/admin/rbac/role-permissions/{roleId}', [RbacAdminController::class, 'syncRolePermissions']);
        $router->post('/open-collab/admin/contributors/{userId}/roles', [RbacAdminController::class, 'assignMemberRoles']);
        $router->post('/open-collab/admin/rbac/overrides/{userId}', [RbacAdminController::class, 'setOverride']);
        $router->delete('/open-collab/admin/rbac/overrides/{userId}/{permissionSlug}', [RbacAdminController::class, 'deleteOverride']);

        $router->get(
            '/open-collab/admin/articles/pending',
            [ArticleApprovalController::class, 'pending']
        );

        $router->post(
            '/open-collab/admin/articles/{id}/approve',
            [ArticleApprovalController::class, 'approve']
        );

        $router->post(
            '/open-collab/admin/articles/{id}/reject',
            [ArticleApprovalController::class, 'reject']
        );

        $router->group(['middleware' => [OnboardingRouteGuard::class]], function ($router) {
            $router->post(
                '/open-collab/pages/{id}/submit',
                [ArticleApprovalController::class, 'submit']
            );

            $router->post(
                '/open-collab/pages/{id}/resubmit',
                [ArticleApprovalController::class, 'resubmit']
            );
        });

        $router->get(
            '/open-collab/payouts/balance',
            [PayoutController::class, 'balance']
        );

        $router->get(
            '/open-collab/payouts',
            [PayoutController::class, 'index']
        );

        $router->post(
            '/open-collab/payouts',
            [PayoutController::class, 'request']
        );

        $router->post(
            '/open-collab/stripe-connect/onboard',
            [StripeConnectController::class, 'onboard']
        );

        $router->get(
            '/open-collab/stripe-connect/status',
            [StripeConnectController::class, 'status']
        );

        $router->get(
            '/open-collab/admin/payouts',
            [PayoutController::class, 'adminIndex']
        );

        $router->get(
            '/open-collab/admin/payouts/stats',
            [AdminPayoutController::class, 'stats']
        );

        $router->post(
            '/open-collab/admin/payouts/{id}/approve',
            [PayoutController::class, 'approve']
        );

        $router->post(
            '/open-collab/admin/payouts/{id}/paid',
            [PayoutController::class, 'markPaid']
        );

        $router->post(
            '/open-collab/admin/payouts/{id}/reject',
            [PayoutController::class, 'reject']
        );

        $router->post(
            '/open-collab/admin/payouts/{id}/retry',
            [PayoutController::class, 'retry']
        );

        $router->get(
            '/open-collab/admin/stripe-webhooks',
            [StripeWebhookController::class, 'adminIndex']
        );

        $router->get(
            '/open-collab/admin/contributors/{userId}/violations',
            [ViolationController::class, 'index']
        );

        $router->post(
            '/open-collab/admin/contributors/{userId}/violations',
            [ViolationController::class, 'store']
        );

        $router->post(
            '/open-collab/admin/violations/{id}/resolve',
            [ViolationController::class, 'resolve']
        );

        $router->post(
            '/open-collab/contributor-requests',
            [ContributorRequestController::class, 'store']
        );

        $router->post(
            '/open-collab/invitations/resend',
            [ResendInvitationController::class, 'resend']
        );

// ── Admin: contributor request queue ─────────────────────────────────
// (add inside the authenticated admin group)
        $router->get(
            '/open-collab/admin/contributor-requests',
            [ContributorRequestController::class, 'index']
        );
        $router->post(
            '/open-collab/admin/contributor-requests/{id}/approve',
            [ContributorRequestController::class, 'approve']
        );
        $router->post(
            '/open-collab/admin/contributor-requests/{id}/reject',
            [ContributorRequestController::class, 'reject']
        );

        $router->get('/communications/subscription/open/{token}',  [SubscriptionCommunicationTrackingController::class, 'open'])->name('subscription-comms.open');
        $router->get('/communications/subscription/click/{token}', [SubscriptionCommunicationTrackingController::class, 'click'])->name('subscription-comms.click');
        $router->get('/subscriptions/{subscriptionId}/communication-history', [SubscriptionCommunicationHistoryController::class, 'index']);
        $router->get('/subscription-communications/{communicationId}/history', [SubscriptionCommunicationHistoryController::class, 'communication']);
        $router->get('/subscription-communications/{id}/schedules', [SubscriptionCommunicationController::class, 'schedules']);
        $router->apiResource('/subscription-communications', SubscriptionCommunicationController::class);
        $router->post('/subscription-communications/{id}/schedules', [SubscriptionCommunicationController::class, 'storeSchedule']);
        $router->put('/subscription-communication-schedules/{id}',  [SubscriptionCommunicationController::class, 'updateSchedule']);
        $router->delete('/subscription-communication-schedules/{id}', [SubscriptionCommunicationController::class, 'destroySchedule']);
        $router->get('/subscription-communications/{subscription_communication}/scopes', [SubscriptionCommunicationScopeController::class, 'index']);
        $router->post('/subscription-communications/{subscription_communication}/scopes', [SubscriptionCommunicationScopeController::class, 'store']);
        $router->delete('/subscription-communication-scopes/{scope}', [SubscriptionCommunicationScopeController::class, 'destroy']);
        $router->get('/subscription-communication-letter-codes', [SubscriptionCommunicationLetterCodeController::class, 'index']);
        $router->post('/subscription-communication-letter-codes', [SubscriptionCommunicationLetterCodeController::class, 'store']);
        $router->put('/subscription-communication-letter-codes/{id}', [SubscriptionCommunicationLetterCodeController::class, 'update']);
        $router->delete('/subscription-communication-letter-codes/{id}', [SubscriptionCommunicationLetterCodeController::class, 'destroy']);

        $router->get(
            '/crm/members/{memberId}/duplicates',
            [CrmDuplicateController::class, 'index']
        );

        $router->get(
            '/crm/members/{memberId}/payments/{paymentId}/refund-preview',
            [CrmPaymentRefundPreviewController::class, 'show']
        );

        $router->get('/crm/countries', [CrmCountryController::class, 'index']);

        $router->post(
            '/crm/members/{memberId}/subscriptions/{subscriptionId}/issues/{issueId}/resolution', [CrmIssueResolutionController::class, 'resolve']);

        $router->post(
            '/crm/members/{memberId}/subscriptions/{subscriptionId}/issues/{issueId}/replace',
            [CrmIssueResolutionController::class, 'replace']);

        $router->post(
            '/crm/members/{memberId}/subscriptions/{subscriptionId}/retention-incentive',
            [CrmSubscriptionRetentionController::class, 'apply'],
        );


        $router->get(
            '/crm/members/{memberId}/duplicates/{duplicateMemberId}/compare',
            [CrmDuplicateController::class, 'compare']
        );

        $router->get(
            '/crm/members/{memberId}/duplicates/{duplicateMemberId}/merge',
            [CrmDuplicateController::class, 'merge']
        );

        $router->get(
            '/crm/members/{memberId}/duplicates/{duplicateMemberId}/conflicts',
            [CrmDuplicateController::class, 'conflicts']
        );

        $router->get(
            '/crm/members/{memberId}/manual-payments',
            [CrmManualPaymentController::class, 'index']
        );

        $router->get(
            '/crm/subscriptions/{subscriptionId}/available-editions',
            [CrmSubscriptionController::class, 'availableEditions']
        );

        $router->get(
            '/crm/subscriptions/{subscriptionId}/available-publications',
            [CrmSubscriptionController::class, 'availablePublications']
        );

        $router->post('/crm/subscriptions/{subscriptionId}/change-edition', [CrmSubscriptionController::class, 'changeEdition']);
        $router->post('/crm/subscriptions/{subscriptionId}/change-publication', [CrmSubscriptionController::class, 'changePublication']);
        $router->get('/crm/subscriptions/{subscriptionId}/changes',  [CrmSubscriptionController::class, 'subscriptionChanges']);
        $router->get('/crm/subscriptions/{subscriptionId}/cancellation-options', [CrmSubscriptionController::class, 'cancellationOptions']);
        $router->get('/crm/subscriptions/{subscriptionId}/refund-options', [CrmSubscriptionController::class, 'refundOptions']);
        $router->get('/crm/subscriptions/{subscriptionId}/suspension-options', [CrmSubscriptionController::class, 'suspensionOptions']);

        $router->post(
            '/crm/subscriptions/{subscriptionId}/stripe-sync/retry',
            [CrmSubscriptionController::class, 'retryStripeSync']
        );

        $router->post(
            '/crm/members/{memberId}/payments/{paymentId}/refund',
            [CrmSubscriptionController::class, 'refundPayment']
        );

        $router->post(
            '/crm/members/{memberId}/payments/bulk-refund',
            [CrmSubscriptionController::class, 'bulkRefundPayments']
        );

        $router->post(
            '/crm/members/{memberId}/manual-payments',
            [CrmManualPaymentController::class, 'store']
        );
        $router->delete(
            '/crm/members/{memberId}/manual-payments/{id}',
            [CrmManualPaymentController::class, 'destroy']
        );

        $router->get(
            '/crm/members/{memberId}/attachments',
            [CrmAttachmentController::class, 'index']
        );
        $router->post(
            '/crm/members/{memberId}/attachments',
            [CrmAttachmentController::class, 'store']
        );
        $router->delete(
            '/crm/members/{memberId}/attachments/{id}',
            [CrmAttachmentController::class, 'destroy']
        );

        $router->post(
            '/crm/members/{memberId}/charging/disable',
            [CrmChargingController::class, 'disable']
        );
        $router->post(
            '/crm/members/{memberId}/charging/enable',
            [CrmChargingController::class, 'enable']
        );

        $router->get('/crm/subscription-offers', [CrmSubscriptionOfferController::class, 'index']);

// Retry a specific failed payment (charging-policy-aware)
        $router->post(
            '/crm/members/{memberId}/payments/{paymentId}/retry',
            [CrmChargingController::class, 'retryPayment']
        );

        // crm
        $router->get('/crm/members', [CrmMemberController::class, 'index']);
        $router->get('/crm/members/filter-options', [CrmMemberController::class, 'filterOptions']);
        $router->get('/crm/members/{id}', [CrmMemberController::class, 'show']);
        $router->post('/crm/members', [CrmMemberController::class, 'store']);
        $router->put('/crm/members/{id}', [CrmMemberController::class, 'update']);
        $router->delete('/crm/members/{id}', [CrmMemberController::class, 'destroy']);
        $router->get('/crm/orders', [CrmOrderController::class, 'index']);
        $router->get('/crm/members/{memberId}/consents', [CrmMemberConsentController::class, 'index']);
        $router->put('/crm/members/{memberId}/consents', [CrmMemberConsentController::class, 'update']);

        $router->get('/crm/members/{memberId}/orders', [CrmSubscriptionController::class, 'ordersForMember']);
        $router->get('/crm/members/{memberId}/communications', [CrmCommunicationsController::class, 'index']);
        $router->get('/crm/members/{memberId}/activity', [CrmSubscriptionController::class, 'activityForMember']);
        $router->get('/crm/members/{memberId}/sar-export', [SarExportController::class, 'export']);
        $router->post('/crm/members/{memberId}/forget', [RtbfController::class, 'forget']);

        $router->get(
            '/crm/subscriptions/{subscriptionId}/history',
            [CrmSubscriptionController::class, 'history'],
        );

        $router->get(
            '/crm/members/{memberId}/subscription-stats',
            [CrmSubscriptionController::class, 'subscriptionStatsForMember'],
        );

        $router->post(
            '/crm/members/{memberId}/subscriptions',
            [CrmSubscriptionController::class, 'createForMember'],
        );

        $router->post('/crm/members/{memberId}/subscriptions/{subscriptionId}/renew',
            [CrmSubscriptionController::class, 'renewForMember']);

        $router->get('/crm/members/{memberId}/subscriptions/{subscriptionId}/switch-preview',
            [CrmSubscriptionController::class, 'switchPreview']);

        $router->post('crm/members/{memberId}/subscriptions/{subscriptionId}/switch',
            [CrmSubscriptionController::class, 'switchProductForMember']);

        $router->post('crm/members/{memberId}/subscriptions/{subscriptionId}/issues/{issueId}/replace',
            [CrmSubscriptionController::class, 'requestIssueReplacement']);

        $router->post('crm/members/{memberId}/subscriptions/{subscriptionId}/suspend',
            [CrmSubscriptionController::class, 'suspendForMember']);

        $router->post('crm/members/{memberId}/subscriptions/{subscriptionId}/unsuspend',
            [CrmSubscriptionController::class, 'unsuspendForMember']);

        $router->get(
            '/crm/members/{memberId}/notes',
            [CrmMemberNoteController::class, 'index'],
        );

        $router->post(
            '/crm/members/{memberId}/notes',
            [CrmMemberNoteController::class, 'store'],
        );

        $router->post(
            '/crm/members/{memberId}/notes/{id}',
            [CrmMemberNoteController::class, 'update'],
        );

        $router->delete(
            '/crm/members/{memberId}/notes/{id}',
            [CrmMemberNoteController::class, 'destroy'],
        );

        $router->get(
            '/crm/billing/stripe-config',
            [StripeConfigController::class, 'config'],
        );

        $router->get(
            '/crm/members/{memberId}/payment-methods',
            [CrmMemberPaymentMethodsController::class, 'index'],
        );

        $router->post(
            '/crm/members/{memberId}/subscriptions/{subscriptionId}/cancel',
            [CrmSubscriptionController::class, 'cancelForMember'],
        );

        $router->post(
            '/crm/members/{memberId}/subscriptions/{subscriptionId}/pause-delivery',
            [CrmSubscriptionController::class, 'pauseDeliveryForMember'],
        );

        $router->post(
            '/crm/members/{memberId}/subscriptions/{subscriptionId}/resume-delivery',
            [CrmSubscriptionController::class, 'resumeDeliveryForMember'],
        );

        $router->post('/crm/members/{memberId}/subscriptions/{subscriptionId}/pause-subscription', [CrmSubscriptionController::class, 'pauseSubscriptionForMember']);
        $router->post('/crm/members/{memberId}/subscriptions/{subscriptionId}/resume-subscription', [CrmSubscriptionController::class, 'resumeSubscriptionForMember']);

        // Admin-only: per-site overrides of individual pause/cancellation
        // policy settings. Gated by 'subscription_policies.override',
        // separate from the crm.subscriptions.* permissions above.
        $router->get(
            '/crm/subscription-policies/overrides',
            [SubscriptionPolicyOverrideController::class, 'index'],
        );
        $router->get(
            '/crm/subscription-policies/{policyClass}/overrides/history',
            [SubscriptionPolicyOverrideController::class, 'history'],
        );
        $router->post(
            '/crm/subscription-policies/overrides',
            [SubscriptionPolicyOverrideController::class, 'store'],
        );
        $router->post(
            '/crm/subscription-policies/overrides/clear',
            [SubscriptionPolicyOverrideController::class, 'clear'],
        );

        $router->get(
            '/crm/members/{memberId}/payments',
            [CrmSubscriptionController::class, 'paymentsForMember'],
        );

        $router->get(
            '/crm/members/{memberId}/subscriptions/{subscriptionId}/issues',
            [CrmSubscriptionController::class, 'issuesForSubscription'],
        );

        $router->get('/crm/subscriptions/plans/{planId}', [CrmSubscriptionController::class, 'getPlan']);


        $router->post(
            '/crm/members/{memberId}/subscriptions/{subscriptionId}/reactivate',
            [CrmSubscriptionController::class, 'reactivateForMember'],
        );

        $router->get('/crm/members/{memberId}/addresses', [CrmAddressController::class, 'index']);

        // Store new address
        $router->post('/crm/members/{memberId}/addresses', [CrmAddressController::class, 'store']);

        // Update existing address
        $router->put('/crm/members/{memberId}/addresses/{id}', [CrmAddressController::class, 'update']);

        // Delete address
        $router->delete('/crm/members/{memberId}/addresses/{id}', [CrmAddressController::class, 'destroy']);

        // Set address as default
        $router->post('/crm/members/{memberId}/addresses/{id}/default', [CrmAddressController::class, 'setDefault']);

        // Briefs
        $router->get('/briefs', [BriefController::class, 'index']);
        $router->post('/briefs', [BriefController::class, 'store']);
        $router->get('/briefs/{id}', [BriefController::class, 'show']);
        $router->put('/briefs/{id}', [BriefController::class, 'update']);
        $router->delete('/briefs/{id}', [BriefController::class, 'destroy']);
        $router->post('/briefs/{id}/clone', [BriefController::class, 'clone']);
        $router->post('/briefs/{id}/convert-article', [BriefController::class, 'convertBriefToArticle']);
        $router->post('/briefs/{id}/schedule', [BriefController::class, 'createSchedule']);
        $router->get('/briefs/{id}/schedule', [BriefController::class, 'getSchedule']);
        $router->put('/briefs/{id}/schedule', [BriefController::class, 'updateSchedule']);
        $router->delete('/briefs/{id}/schedule', [BriefController::class, 'deleteSchedule']);
        $router->post('/brief-preset', [BriefPresetController::class, 'store']);
        $router->get('/brief-preset', [BriefPresetController::class, 'index']);
        $router->put('/brief-preset/{id}', [BriefPresetController::class, 'update']);
        $router->delete('/brief-preset/{id}', [BriefPresetController::class, 'destroy']);
        $router->get('/brief-preset/{id}', [BriefPresetController::class, 'show']);
        $router->post('/brief/from-preset/{id}', [BriefPresetController::class, 'createFromPreset']);
        $router->get('briefs/{briefId}/requests', [BriefAssignmentRequestController::class, 'index']);
        $router->post('briefs/{briefId}/requests/{requestId}/respond', [BriefAssignmentRequestController::class, 'respond']);
        $router->post('briefs/{briefId}/requests/{requestId}/approve', [BriefAssignmentRequestController::class, 'approve']);


        // Brief attachments
        $router->post('/briefs/{id}/attachments', [BriefController::class, 'addAttachment']);
        $router->delete('/briefs/{id}/attachments/{attachmentId}', [BriefController::class, 'deleteAttachment']);
        $router->put('/briefs/{id}/attachments/{attachmentId}', [BriefController::class, 'updateAttachment']);
        $router->post('/briefs/{id}/upload', [BriefController::class, 'uploadAttachment']);

        // Brief comments
        $router->post('/briefs/{id}/comments', [BriefController::class, 'addComment']);
        $router->delete('/briefs/{id}/comments/{commentId}', [BriefController::class, 'deleteComment']);
        $router->put('/briefs/{id}/comments/{commentId}', [BriefController::class, 'updateComment']);

        // Brief conversion
        $router->post('/briefs/{id}/convert', [BriefController::class, 'convertToPage']);
        $router->post('/briefs/{id}/archive', [BriefController::class, 'archive']);

        // Brief Templates
        $router->get('/briefs/templates', [BriefController::class, 'getTemplates']);
        $router->post('/briefs/templates/{templateId}/create', [BriefController::class, 'createFromTemplate']);
        $router->post('/briefs/{id}/save-template', [BriefController::class, 'saveAsTemplate']);

// Collaborators
        $router->get('/briefs/{id}/collaborators', [BriefController::class, 'getCollaborators']);
        $router->post('/briefs/{id}/collaborators', [BriefController::class, 'addCollaborator']);
        $router->delete('/briefs/{id}/collaborators/{collaboratorId}', [BriefController::class, 'removeCollaborator']);
        $router->put('/briefs/{id}/collaborators/{collaboratorId}', [BriefController::class, 'updateCollaborator']);

// Tasks
        $router->get('/briefs/{id}/tasks', [BriefController::class, 'getTasks']);
        $router->post('/briefs/{id}/tasks', [BriefController::class, 'createTask']);
        $router->put('/briefs/{id}/tasks/{taskId}', [BriefController::class, 'updateTask']);
        $router->delete('/briefs/{id}/tasks/{taskId}', [BriefController::class, 'deleteTask']);
        $router->get('/brief-subtask', [BriefController::class, 'searchTasks']);

// Versions
        $router->get('/briefs/{id}/versions', [BriefController::class, 'getVersions']);
        $router->post('/briefs/{id}/versions/{versionId}/restore', [BriefController::class, 'restoreVersion']);
        $router->get('/briefs/{id}/versions/compare', [BriefController::class, 'compareVersions']);

// Status Management
        $router->put('/briefs/{id}/status', [BriefController::class, 'updateStatus']);

// Duplicate
        $router->post('/briefs/{id}/duplicate', [BriefController::class, 'clone']);

// Activity Log
        $router->get('/briefs/{id}/activity', [BriefController::class, 'getActivityLog']);

// Comment Resolution
        $router->post('/briefs/{id}/comments/{commentId}/resolve', [BriefController::class, 'resolveComment']);
        $router->post('/briefs/{id}/comments/{commentId}/unresolve', [BriefController::class, 'unresolveComment']);

// Relationships
        $router->get('/briefs/{id}/relationships', [BriefController::class, 'getRelationships']);
        $router->post('/briefs/{id}/relationships', [BriefController::class, 'addRelationship']);
        $router->delete('/briefs/{id}/relationships/{relationshipId}', [BriefController::class, 'removeRelationship']);

        //Workflow
        $router->post('/briefs/{id}/workflow', [BriefController::class, 'addWorkflowChange']);
        $router->get('/briefs/{id}/workflow', [BriefController::class, 'getWorkflowHistory']);

        //Deadlines
        $router->post('/briefs/{id}/deadline', [BriefController::class, 'setDeadline']);
        $router->get('/briefs/{id}/deadline', [BriefController::class, 'getDeadline']);
        $router->delete('/briefs/{id}/deadline', [BriefController::class, 'deleteDeadline']);

// Bulk Operations
        $router->post('/briefs/bulk/status', [BriefController::class, 'bulkUpdateStatus']);
        $router->post('/briefs/bulk/assign', [BriefController::class, 'bulkAssign']);
        $router->post('/briefs/bulk/delete', [BriefController::class, 'bulkDelete']);

// Export
        $router->get('/briefs/{id}/export/pdf', [BriefController::class, 'exportAsPdf']);
        $router->get('/briefs/{id}/export/word', [BriefController::class, 'exportAsWord']);

        $router->get('/members', MemberController::class, 'search');

        $router->get('/pages', [PageController::class, 'index']);
        $router->post('/pages', PageController::class, 'store', [AuthenticateWithToken::class]);
        $router->post('/pages/bulk-update', PageController::class, 'bulkUpdate', [AuthenticateWithToken::class]);
        $router->get('/pages/block-types', PageController::class, 'getAvailableBlockTypes', [AuthenticateWithToken::class]);
        $router->get('/pages/{id}', PageController::class, 'show');
        $router->put('/pages/{id}', PageController::class, 'update');
        $router->delete('/pages/{id}', PageController::class, 'destroy');
        $router->post('/pages/{id}/unpublish', PageController::class, 'unpublish');
        $router->get('/block-types', PageController::class, 'getAvailableBlockTypes');
        $router->post('/pages/{id}/duplicate', PageController::class, 'duplicate');
        $router->post('/pages/bulk-delete', PageController::class, 'bulkDelete');
        $router->post('/pages/bulk-update-status', PageController::class, 'bulkUpdateStatus');
        $router->post('/pages/{id}/clone-to-site', PageController::class, 'cloneToSite');
        $router->get('/pages/calendar', [PageController::class, 'getCalendarPages']);
        $router->get('/featured-pages', PageController::class, 'getFeaturedPages');
        $router->put('/pages/{id}/schedule', [PageController::class, 'updateSchedule']);
        $router->post('/pages/{id}/approve-with-decision', [PageController::class, 'approveWithDecision']);
        $router->get('/pages/{id}/premium-price-recommendation', [PageController::class, 'premiumPriceRecommendation']);

        $router->get('/rewards', [RewardsAdminController::class, 'index']);
        $router->get('/rewards/search', [RewardsAdminController::class, 'search']);
        $router->get('/rewards/{rewardId}', [RewardsAdminController::class, 'show']);
        $router->put('/rewards/{rewardId}', [RewardsAdminController::class, 'update']);
        $router->post('/rewards/{rewardId}/decline', [RewardsAdminController::class, 'decline']);
        $router->get('/reward-audit-logs', [RewardAuditLogController::class, 'index']);
        $router->get('/reward-audit-logs/reward/{rewardId}', [RewardAuditLogController::class, 'getForReward']);
        $router->get('/reward-audit-logs/action/{action}', [RewardAuditLogController::class, 'getByAction']);
        $router->get('/reward-audit-logs/date-range', [RewardAuditLogController::class, 'getByDateRange']);

        $router->get('/reward-definitions/statistics', [RewardDefinitionsAdminController::class, 'getStatistics']);
        $router->get('/rewards/statistics', [RewardsAdminController::class, 'getStatistics']);
        $router->get('/offers/statistics', [ProductOfferController::class, 'getStatistics']);
        $router->get('/offers/statistics/{type}', [OfferStatisticsDetailController::class, 'show']);
        $router->post('/offers/bulk/publish', [ProductOfferController::class, 'bulkPublish']);
        $router->post('/offers/bulk/delete', [ProductOfferController::class, 'bulkDelete']);

        $router->get('/reward-definitions', [RewardDefinitionsAdminController::class, 'index']);
        $router->get('/reward-definitions/search', [RewardDefinitionsAdminController::class, 'search']);
        $router->get('/reward-definitions/{definitionId}', [RewardDefinitionsAdminController::class, 'show']);
        $router->put('/reward-definitions/{definitionId}', [RewardDefinitionsAdminController::class, 'update']);
        $router->post('/reward-definitions', [RewardDefinitionsAdminController::class, 'create']);
        $router->delete('/reward-definitions/{definitionId}', [RewardDefinitionsAdminController::class, 'delete']);

        // Pipeline routes
        $router->get('/pipeline', [PipelineController::class, 'index']);
        $router->get('/pipeline/metrics', [PipelineController::class, 'metrics']);
        $router->put('/pipeline/{id}/stage', [PipelineController::class, 'updateStage']);
        $router->post('/pipeline/bulk-update-stage', [PipelineController::class, 'bulkUpdateStage']);

        $router->get('/campaigns', [CampaignController::class, 'index']);
        $router->get('/campaigns/active', [CampaignController::class, 'getActive']);
        $router->get('/campaigns/{id}', [CampaignController::class, 'show']);
        $router->post('/campaigns', [CampaignController::class, 'create']);
        $router->put('/campaigns/{id}', [CampaignController::class, 'update']);
        $router->delete('/campaigns/{id}', [CampaignController::class, 'delete']);
        $router->post('/campaigns/{id}/pause', [CampaignController::class, 'pause']);
        $router->post('/campaigns/{id}/resume', [CampaignController::class, 'resume']);
        $router->post('/campaigns/{id}/clone', [CampaignController::class, 'clone']);

        $router->get('/campaign-analytics/campaigns', [CampaignAnalyticsApiController::class, 'campaigns']);
        $router->get('/campaign-analytics/campaigns/{campaignId}/summary', [CampaignAnalyticsApiController::class, 'summary']);
        $router->get('/campaign-analytics/campaigns/{campaignId}/audiences', [CampaignAnalyticsApiController::class, 'audiences']);
        $router->get('/campaign-analytics/campaigns/{campaignId}/blocks', [CampaignAnalyticsApiController::class, 'blocks']);
        $router->get('/campaign-analytics/campaigns/{campaignId}/variants', [CampaignAnalyticsApiController::class, 'variants']);
        $router->get('/campaign-analytics/audiences', [CampaignAnalyticsApiController::class, 'audienceList']);

        // Payment routes
        $router->get('/orders/{id}/payments', [OrderController::class, 'payments']);
        $router->post('/orders/{id}/payments', [OrderController::class, 'createPayment']);

// Payment method routes
        $router->get('/payment-methods', [PaymentMethodController::class, 'index']);
        $router->get('/payment-methods/active', [PaymentMethodController::class, 'active']);
        $router->post('/payment-methods', [PaymentMethodController::class, 'store']);
        $router->get('/payment-methods/{id}', [PaymentMethodController::class, 'show']);
        $router->put('/payment-methods/{id}', [PaymentMethodController::class, 'update']);
        $router->delete('/payment-methods/{id}', [PaymentMethodController::class, 'destroy']);

        $router->get('/email-themes', [EmailThemeController::class, 'index']);
        $router->get('/email-themes/preview/{id}', [EmailThemeController::class, 'preview']);
        $router->post('/email-themes/preview', [EmailThemeController::class, 'previewFromData']);
        $router->get('/email-themes/active', [EmailThemeController::class, 'getActive']);
        $router->post('/email-themes', [EmailThemeController::class, 'store']);
        $router->get('/email-themes/{id}', [EmailThemeController::class, 'show']);
        $router->put('/email-themes/{id}', [EmailThemeController::class, 'update']);
        $router->delete('/email-themes/{id}', [EmailThemeController::class, 'destroy']);
        $router->post('/email-themes/{id}/set-default', [EmailThemeController::class, 'setDefault']);
        $router->get('/email-themes/{id}/alternatives', [EmailThemeController::class, 'alternatives']);
        $router->post('/email-themes/{id}/duplicate', [EmailThemeController::class, 'duplicate']);
        $router->post('/email-themes/bulk-delete', [EmailThemeController::class, 'bulkDelete']);

        $router->get('/email-templates/{id}/versions', [EmailTemplateController::class, 'versions']);
        $router->get('/email-templates/{id}/versions/{id}', [EmailTemplateController::class, 'index']);
        $router->post('/email-templates/{id}/versions/{id}/restore', [EmailTemplateController::class, 'restoreVersion']);

        $router->get('/email-templates', [EmailTemplateController::class, 'index']);
        $router->post('/email-templates', [EmailTemplateController::class, 'store']);
        $router->post('/email-templates/preview', [EmailTemplateController::class, 'previewFromData']);
        $router->get('/email-templates/{id}/preview', [EmailTemplateController::class, 'preview']);
        $router->get('/email-templates/{id}', [EmailTemplateController::class, 'show']);
        $router->put('/email-templates/{id}', [EmailTemplateController::class, 'update']);
        $router->delete('/email-templates/{id}', [EmailTemplateController::class, 'destroy']);
        $router->post('/email-templates/{id}/duplicate', [EmailTemplateController::class, 'duplicate']);

        $router->get('/payments', [PaymentController::class, 'index']);
        $router->get('/payments/by-transaction', [PaymentController::class, 'byTransaction']);
        $router->get('/payments/total-collected', [PaymentController::class, 'totalCollected']);
        $router->get('/payments/{id}', [PaymentController::class, 'show']);
        $router->post('/payments/{id}/process', [PaymentController::class, 'process']);
        $router->post('/payments/{id}/complete', [PaymentController::class, 'complete']);
        $router->post('/payments/{id}/fail', [PaymentController::class, 'fail']);
        $router->post('/payments/{id}/cancel', [PaymentController::class, 'cancel']);
        $router->post('/payments/{id}/retry', [PaymentController::class, 'retry']);
        $router->post('/payments/{id}/refund', [PaymentController::class, 'refund']);

        //payment subscriptions
        $router->get('/payments/subscription-failures', [PaymentController::class, 'subscriptionFailures']);

        $router->get('/subscriptions', [SubscriptionController::class, 'index']);
        $router->get('/subscriptions/payments', [SubscriptionController::class, 'payments']);
        $router->get('/subscriptions/plans', [SubscriptionController::class, 'plans']);
        $router->post('/subscriptions/plans', [SubscriptionController::class, 'createPlan']);
        $router->put('/subscriptions/plans/{id}', [SubscriptionController::class, 'updatePlan']);
        $router->post('/subscriptions/plans/{id}', [SubscriptionController::class, 'updatePlan']);
        $router->delete('/subscriptions/plans/{id}', [SubscriptionController::class, 'deletePlan']);

        $router->get('/subscriptions/plans/{planId}/subscribers', [SubscriptionPlanSubscriberController::class, 'planSubscribers']);
        $router->get('/subscriptions/{subscriptionId}', [SubscriptionPlanSubscriberController::class, 'show']);
        $router->get('/subscriptions/{subscriptionId}/preferences', [SubscriptionPlanSubscriberController::class, 'preferences']);
        $router->post('/print-runs/bulk-cancel', [PrintRunController::class, 'bulkCancel']);
        $router->put('/print-runs/{printRunId}/retry', [PrintRunController::class, 'retry']);

        $router->get('/issues/{issueId}/print-runs', [PrintRunController::class, 'listByIssue']);
        $router->get('/print-runs', [PrintRunController::class, 'index']);
        $router->get('/print-runs/{printRunId}', [PrintRunController::class, 'show']);
        $router->put('/print-runs/{printRunId}/cancel', [PrintRunController::class, 'cancel']);

        $router->get('/print-fulfillments', [PrintFulfillmentController::class, 'index']);
        $router->get('/print-fulfillments/{fulfillmentId}', [PrintFulfillmentController::class, 'show']);
        $router->get('/batches/{batchId}/print-fulfillments', [PrintFulfillmentController::class, 'listByBatch']);
        $router->put('/print-fulfillments/{fulfillmentId}/tracking', [PrintFulfillmentController::class, 'updateTracking']);

        $router->get('/print-batches', [PrintBatchReportController::class, 'index']);
        $router->get('/print-batches/{printBatchId}', [PrintBatchReportController::class, 'show']);
        $router->post('/print-batches/{printBatchId}/export', [PrintBatchReportController::class, 'trigger']);
        $router->get('/print-batches/{printBatchId}/download', [PrintBatchReportController::class, 'download']);

        // Ad-hoc fulfilment file generation (Phase 1: PrintBatch export only).
        // Download reuses PrintBatchReportController::download above — an
        // ad-hoc-generated PrintBatch file is the same file/record the
        // scheduled pipeline produces, so there is no separate download route.
        $router->get('/ad-hoc-fulfilment-requests', [AdHocFulfilmentController::class, 'index']);
        $router->get('/ad-hoc-fulfilment-requests/{requestId}', [AdHocFulfilmentController::class, 'show']);
        $router->post('/ad-hoc-fulfilment-requests/print-batches', [AdHocFulfilmentController::class, 'generateForDateRange']);
        $router->post('/ad-hoc-fulfilment-requests/print-batches/{printBatchId}', [AdHocFulfilmentController::class, 'generateForPrintBatch']);

        $router->get('/label-runs', [LabelRunReportController::class, 'index']);
        $router->get('/label-runs/{labelRunId}', [LabelRunReportController::class, 'show']);
        $router->post('/label-runs/{labelRunId}/generate', [LabelRunReportController::class, 'trigger']);
        $router->get('/label-runs/{labelRunId}/download', [LabelRunReportController::class, 'download']);

        $router->get('/print-vendor-connections', [PrintVendorConnectionController::class, 'index']);
        $router->post('/print-vendor-connections', [PrintVendorConnectionController::class, 'store']);
        $router->get('/print-vendor-connections/{id}', [PrintVendorConnectionController::class, 'show']);
        $router->put('/print-vendor-connections/{id}', [PrintVendorConnectionController::class, 'update']);
        $router->delete('/print-vendor-connections/{id}', [PrintVendorConnectionController::class, 'destroy']);
        $router->post('/print-vendor-connections/{id}/test', [PrintVendorConnectionController::class, 'testConnection']);

        $router->get('/workflow-runs', [WorkflowRunController::class, 'index']);
        $router->get('/workflow-runs/{runId}', [WorkflowRunController::class, 'show']);


        $router->post('/subscriptions/plans/bulk-toggle-active', [SubscriptionController::class, 'bulkToggleActive']);

        // Subscription premium access management
        $router->post(
            '/subscriptions/{subscriptionId}/premium-access/grant',
            [AdminSubscriptionPremiumAccessController::class, 'grant']
        );
        $router->post(
            '/subscriptions/{subscriptionId}/premium-access/revoke',
            [AdminSubscriptionPremiumAccessController::class, 'revoke']
        );
        $router->put(
            '/subscriptions/premium-access/{id}',
            [AdminSubscriptionPremiumAccessController::class, 'update']
        );
        $router->delete(
            '/subscriptions/premium-access/{id}',
            [AdminSubscriptionPremiumAccessController::class, 'destroy']
        );

        $router->get('/subscriptions/{subscriptionId}/payments', [PaymentController::class, 'subscriptionPayments']);
        $router->post('/subscriptions/{subscriptionId}/payments', [PaymentController::class, 'createSubscriptionPayment']);

        $router->post('/products/find-matches', [ProductMatchingController::class, 'findMatches']);
        $router->get('/products/{id}/pages', [ProductController::class, 'pages']);

        // Approval workflow routes
        $router->post('/pages/{id}/approve', [PageController::class, 'approve']);
        $router->post('/pages/{id}/reject', [PageController::class, 'reject']);
        $router->post('/pages/{id}/put-on-hold', [PageController::class, 'putOnHold']);
        $router->post('/pages/{id}/make-private', [PageController::class, 'makePrivate']);
        $router->post('/pages/{id}/make-internal', [PageController::class, 'makeInternal']);
        $router->post('/pages/bulk-approve', [PageController::class, 'bulkApprove']);
        $router->post('/pages/bulk-schedule', [PageController::class, 'bulkSchedule']);
        $router->post('/pages/bulk-add-tags', [PageController::class, 'bulkAddTags']);
        $router->post('/pages/bulk-remove-tags', [PageController::class, 'bulkRemoveTags']);
        $router->post('/pages/bulk-change-author', [PageController::class, 'bulkChangeAuthor']);
        $router->post('/pages/bulk-add-contributors', [PageController::class, 'bulkAddContributors']);
        $router->post('/pages/bulk-remove-contributors', [PageController::class, 'bulkRemoveContributors']);
        $router->post('/pages/bulk-update-regions', [PageController::class, 'bulkUpdateRegions']);
        $router->post('/pages/bulk-clone', [PageController::class, 'bulkClone']);

        $router->get('/pages/{pageId}/custom-fields/grouped', CustomFieldDefinitionController::class, 'getCustomFieldsGrouped');

        // Categories API
        $router->get('/categories', CategoryController::class, 'index');
        $router->post('/categories', CategoryController::class, 'store');
        $router->get('/categories/{id}', CategoryController::class, 'show');
        $router->put('/categories/{id}', CategoryController::class, 'update');
        $router->delete('/categories/{id}', CategoryController::class, 'destroy');
        $router->get('/categories/{id}/check-delete', CategoryController::class, 'checkDelete');
        $router->post('/categories/{id}/duplicate', CategoryController::class, 'duplicate');
        $router->post('/categories/bulk-delete', [CategoryController::class, 'bulkDelete']);

        // Brands
        $router->get('/brands', BrandController::class, 'index');
        $router->post('/brands', BrandController::class, 'store');
        $router->get('/brands/{id}', BrandController::class, 'show');
        $router->put('/brands/{id}', BrandController::class, 'update');
        $router->delete('/brands/{id}', BrandController::class, 'destroy');
        $router->get('/brands/{id}/check-delete', BrandController::class, 'checkDelete');
        $router->get('/brands/{id}/alternatives', BrandController::class, 'alternatives');
        $router->post('/brands/merge', BrandController::class, 'merge');
        $router->get('/brands/active', BrandController::class, 'active');
        $router->post('/brands/{id}/duplicate', BrandController::class, 'duplicate');
        $router->post('/brands/bulk-delete', [BrandController::class, 'bulkDelete']);

        //Orders
        $router->get('/orders', OrderController::class, 'index');
        $router->post('orders', OrderController::class, 'store');
        $router->get('/orders/by-status', OrderController::class, 'byStatus');
        $router->get('/orders/revenue', OrderController::class, 'revenue');
        $router->get('/orders/by-user/{userId}', OrderController::class, 'byUser');
        $router->get('/orders/{id}', OrderController::class, 'show');
        $router->put('/orders/{id}', OrderController::class, 'update');
        $router->put('/orders/{id}/items', OrderController::class, 'updateItems');
        $router->delete('/orders/{id}', OrderController::class, 'destroy');
        $router->get('/orders/{id}/refunds', OrderController::class, 'refunds');
        $router->get('/orders/{orderId}/refunds/remaining', RefundController::class, 'remainingAmount');
        $router->post('/orders/{id}/cancel', OrderController::class, 'cancel');
        $router->post('/orders/{id}/complete', OrderController::class, 'complete');
        $router->post('/orders/{id}/refund', [OrderController::class, 'refund']);
        $router->post('/orders/{id}/duplicate', OrderController::class, 'duplicate');
        $router->post('/orders/bulk-status', [OrderController::class, 'bulkUpdateStatus']);

        //Refunds
        $router->post('refunds', RefundController::class, 'store');
        $router->post('refunds/{refundId}/cancel', RefundController::class, 'cancel');
        $router->get('/refunds', RefundController::class, 'index');


        // Tags API
        $router->get('/tags', TagController::class, 'index');
        $router->get('/tags/cloud', [TagController::class, 'cloud']);
        $router->post('/tags', TagController::class, 'store');
        $router->post('/tags/merge', TagController::class, 'merge');
        $router->get('/tags/{id}', TagController::class, 'show');
        $router->put('/tags/{id}', TagController::class, 'update');
        $router->delete('/tags/{id}', TagController::class, 'destroy');
        $router->post('/tags/cleanup', TagController::class, 'cleanup');
        $router->get('/featured-tags', TagController::class, 'featured');
        $router->get('/popular-tags', TagController::class, 'popular');
        $router->get('/tags/{id}/check-delete', TagController::class, 'checkDelete');
        $router->post('/tags/{id}/duplicate', TagController::class, 'duplicate');
        $router->post('/tags/bulk-delete', [TagController::class, 'bulkDelete']);


        // Custom Fields API
        $router->get('/custom-fields', CustomFieldDefinitionController::class, 'index');
        $router->get('/custom-fields/grouped', CustomFieldDefinitionController::class, 'grouped');
        $router->get('/custom-fields/required', CustomFieldDefinitionController::class, 'required');
        $router->get('/custom-fields/searchable', CustomFieldDefinitionController::class, 'searchable');
        $router->post('/custom-fields', CustomFieldDefinitionController::class, 'store');
        $router->get('/custom-fields/{id}', CustomFieldDefinitionController::class, 'show');
        $router->put('/custom-fields/{id}', CustomFieldDefinitionController::class, 'update');
        $router->delete('/custom-fields/{id}', CustomFieldDefinitionController::class, 'destroy');

        // Menu
        $router->get('/menu', MenuController::class, 'index');
        $router->post('/menu', MenuController::class, 'store');
        $router->get('/menu/{id}', MenuController::class, 'show');
        $router->get('/menu/{id}/hierarchy', MenuController::class, 'hierarchy');
        $router->get('/menu/slug/{slug}', MenuController::class, 'getMenuBySlug');
        $router->put('/menu/{id}', MenuController::class, 'update');
        $router->delete('/menu/{id}', MenuController::class, 'destroy');

        // Images
        $router->get('/images', ImageController::class, 'index');
        $router->post('/images', ImageController::class, 'store');
        $router->get('/images/{id}', ImageController::class, 'show');
        $router->put('/images/{id}', ImageController::class, 'update');
        $router->delete('/images/{id}', ImageController::class, 'destroy');
        $router->post('/images/{id}/duplicate', ImageController::class, 'duplicate');
        $router->post('/images/bulk-archive', [ImageController::class, 'bulkArchive']);
        $router->post('/images/{id}/archive', [ImageController::class, 'archive']);
        $router->post('/images/{id}/unarchive', [ImageController::class, 'unarchive']);

        //bulk-archive

        // Bulk operations
        $router->delete('/images/bulk', ImageController::class, 'bulkDestroy');

// Utility routes
        $router->get('/image-recent', ImageController::class, 'recent');
        $router->get('/images/statistics', ImageController::class, 'statistics');
        $router->get('/images/unused', ImageController::class, 'unused');
        $router->post('/images/cleanup', ImageController::class, 'cleanup');

// Usage tracking
        $router->post('/image-track-usage', ImageController::class, 'trackUsage');
        $router->post('/image-remove-usage', ImageController::class, 'removeUsage');

// Category management
        $router->get('/image-categories', ImageController::class, 'categories');
        $router->post('/image-categories', ImageController::class, 'createCategory');

// Author API Routes
        $router->get('/authors', AuthorController::class, 'index');
        $router->get('/authors/active', AuthorController::class, 'getActive');
        $router->post('/authors', AuthorController::class, 'store');
        $router->post('/authors/merge', AuthorController::class, 'merge');
        $router->get('/authors/{id}/overrides', AuthorController::class, 'overrides');
        $router->delete('/authors/{id}/overrides/{field}', AuthorController::class, 'removeOverride');
        $router->get('/authors/{id}', AuthorController::class, 'show');
        $router->put('/authors/{id}', AuthorController::class, 'update');
        $router->delete('/authors/{id}', AuthorController::class, 'destroy');
        $router->get('/authors/{id}/check-delete', AuthorController::class, 'checkDelete');
        $router->post('/authors/duplicate/{id}', AuthorController::class, 'duplicate');
        $router->post('/authors/bulk-delete', [AuthorController::class, 'bulkDelete']);


//products
        $router->get('/products', [ProductController::class, 'index']);
        $router->post('/products', [ProductController::class, 'store']);
        $router->get('/products/{id}', [ProductController::class, 'show']);
        $router->put('/products/{id}', [ProductController::class, 'update']);
        $router->delete('/products/{id}', [ProductController::class, 'destroy']);
        $router->post('/products/{id}/duplicate', [ProductController::class, 'duplicate']);
        $router->get('/products/{id}/price-history', [ProductController::class, 'priceHistory']);

        //product offers
        $router->get('/offers/{id}', ProductOfferController::class, 'index');
        $router->get('/products/{productId}/offers', ProductOfferController::class, 'index');
        $router->get('/categories/{categoryId}/offers', [ProductOfferController::class, 'categoryOffers']);
        $router->post('/products/{productId}/offers', ProductOfferController::class, 'store');
        $router->put('/products/{productId}/offers/{offerId}', [ProductOfferController::class, 'update']);
        $router->delete('/products/{productId}/offers/{offerId}', ProductOfferController::class, 'destroy');
//        $router->post('/products/{id}/duplicate', ProductOfferController::class, 'duplicate');
//        $router->get('/products/{id}/price-history', ProductOfferController::class, 'priceHistory');
        $router->get('/offers', [ProductOfferController::class, 'allOffers']);
        $router->post('/products/{productId}/offers/{offerId}/publish', [ProductOfferController::class, 'publish']);
        $router->post('/products/{productId}/offers/{offerId}/reject', [ProductOfferController::class, 'reject']);

        //product bundles
        $router->get('/bundles', ProductOfferBundleController::class, 'index');
        $router->get('/bundles/{bundleId}', [ProductOfferBundleController::class, 'show']);
        $router->post('/bundles', ProductOfferBundleController::class, 'store');
        $router->put('/bundles/{bundleId}', [ProductOfferBundleController::class, 'update']);
        $router->delete('/bundles/{bundleId}', ProductOfferBundleController::class, 'destroy');
        $router->post('/bundles/{bundleId}/publish', [ProductOfferBundleController::class, 'publish']);
        $router->post('/bundles/{bundleId}/reject', [ProductOfferBundleController::class, 'reject']);
        $router->post('/bundles/bulk/publish', [ProductOfferBundleController::class, 'bulkPublish']);
        $router->post('/bundles/bulk/delete', [ProductOfferBundleController::class, 'bulkDelete']);

        //merchants
        $router->get('/merchants', MerchantController::class, 'index');
        $router->post('/merchants', MerchantController::class, 'store');
        $router->get('/merchants/{id}', MerchantController::class, 'show');
        $router->put('/merchants/{id}', MerchantController::class, 'update');
        $router->delete('/merchants/{id}', MerchantController::class, 'destroy');
        $router->post('/merchants/{id}/duplicate', MerchantController::class, 'duplicate');
        $router->get('/merchants/{id}/price-history', MerchantController::class, 'priceHistory');
        $router->post('/merchants/{id}/toggle-status', [MerchantController::class, 'toggleStatus']);
        $router->post('/merchants/bulk-update-status', [MerchantController::class, 'bulkUpdateStatus']);
        $router->post('/merchants/bulk-delete', [MerchantController::class, 'bulkDelete']);
        $router->get('/merchants/active', [MerchantController::class, 'active']);
        $router->get('/merchants/statistics', [MerchantController::class, 'statistics']);
        $router->get('/merchants/{merchantId}/notes', [MerchantController::class, 'getNotes']);
        $router->put('/merchants/notes/{id}', [MerchantController::class, 'updateNote']);
        $router->delete('/merchants/notes/{id}', [MerchantController::class, 'deleteNote']);
        $router->post('/merchants/{merchantId}/notes', [MerchantController::class, 'createNote']);
        $router->get('/merchants/{id}/transactions', [MerchantController::class, 'getTransactions']);


        // merchant contacts
        $router->get('/merchant-contacts', MerchantContactController::class, 'index');
        $router->post('/merchant-contacts', MerchantContactController::class, 'store');
        $router->get('/merchant-contacts/{id}', MerchantContactController::class, 'show');
        $router->put('/merchant-contacts/{id}', MerchantContactController::class, 'update');
        $router->delete('/merchant-contacts/{id}', MerchantContactController::class, 'destroy');
        $router->get('/merchants/{merchantId}/contacts', [MerchantContactController::class, 'getByMerchant']);

        //merchant feeds
        $router->get('/merchants/{merchantId}/feeds', [MerchantProductFeedController::class, 'index']);
        $router->post('/merchants/{merchantId}/feeds', [MerchantProductFeedController::class, 'store']);
        $router->get('/merchants/{merchantId}/feeds/{feedId}', [MerchantProductFeedController::class, 'show']);
        $router->put('/merchants/{merchantId}/feeds/{feedId}', [MerchantProductFeedController::class, 'update']);
        $router->delete('/merchants/{merchantId}/feeds/{feedId}', [MerchantProductFeedController::class, 'destroy']);
        $router->get('/merchants/{merchantId}/feeds/{feedId}/download', [MerchantProductFeedController::class, 'download']);
        $router->post('/merchants/{merchantId}/feeds/{feedId}/fetch', [MerchantProductFeedController::class, 'fetch']);

        //variants
        // Variant listing and management
        $router->get('/variants', [VariantController::class, 'index']);
        $router->post('/variants', [VariantController::class, 'store']);
        $router->get('/variants/{id}', [VariantController::class, 'show']);
        $router->put('/variants/{id}', [VariantController::class, 'update']);
        $router->delete('/variants/{id}', [VariantController::class, 'destroy']);

        // Variant images
        $router->put('/variants/{id}/images', [VariantController::class, 'updateImages']);

        // Variant status toggle
        $router->put('/variants/{id}/toggle-status', [VariantController::class, 'toggleStatus']);

        // Product variants
        $router->get('/products/{id}/variants', [ProductController::class, 'variants']);
        $router->put('/products/{productId}/variants/{variantId}', [ProductController::class, 'updateVariant']);
        $router->delete('/products/{productId}/variants/{variantId}', [ProductController::class, 'deleteVariant']);
        $router->put('/products/{productId}/variants/{variantId}/images', [ProductController::class, 'updateVariantImages']);

        // Product merchants
        $router->get('/products/merchants', [ProductController::class, 'merchants']);

        $router->get('/specification-groups', [ProductController::class, 'specificationGroups']);


        $router->get('/users', UserController::class, 'index');
        $router->post('/users', UserController::class, 'store');
        $router->get('/users/{id}', UserController::class, 'show');
        $router->put('/users/{id}', UserController::class, 'update');
        $router->delete('/users/{id}', UserController::class, 'destroy');


        // Page History routes
        $router->get('/pages/{pageId}/history', PageHistoryController::class, 'index');
        $router->get('/history/{id}', PageHistoryController::class, 'show');
        $router->get('/history/recent', PageHistoryController::class, 'recent');
        $router->get('/users/{userId}/history', PageHistoryController::class, 'userHistory');
        $router->post('/history/{historyId}/restore', PageHistoryController::class, 'restore');


        $router->get('/page-grids', PageGridController::class, 'index');
        $router->post('/page-grids', PageGridController::class, 'store');
        $router->get('/page-grids/slug/{slug}', PageGridController::class, 'showBySlug');
        $router->get('/page-grids/{id}', PageGridController::class, 'show');
        $router->put('/page-grids/{id}', PageGridController::class, 'update');
        $router->delete('/page-grids/{id}', PageGridController::class, 'destroy');
        $router->get('/page-grids/{id}/history', [PageGridController::class, 'history']);

        // page grids
        $router->post('/page-grids/{id}/restore', PageGridController::class, 'restore');
        $router->delete('/page-grids/{id}/force', PageGridController::class, 'forceDestroy');
        $router->post('/page-grids/{id}/duplicate', PageGridController::class, 'duplicate');
        $router->post('/page-grids/{id}/toggle-active', PageGridController::class, 'toggleActive');

        // In your routes file, add these:
        $router->post('/page-grids/{id}/assign-pages', [PageGridController::class, 'assignPages']);
        $router->get('/page-grids/{id}/assigned-pages', [PageGridController::class, 'getAssignedPages']);

        $router->post('/page-grids/{id}/pages', PageGridController::class, 'addPage');
        $router->delete('/page-grids/{id}/pages/{pageIndex}', PageGridController::class, 'removePage');
        $router->put('/page-grids/{id}/pages/{pageIndex}', PageGridController::class, 'updatePage');
        $router->post('/page-grids/{id}/pages/reorder', PageGridController::class, 'reorderPages');

        // Sites
        $router->get('/sites', [SiteController::class, 'index']);
        $router->get('/sites/current', [SiteController::class, 'getCurrent']);
        $router->get('/sites/{id}', [SiteController::class, 'show']);
        $router->post('/sites', [SiteController::class, 'create']);
        $router->put('/sites/{id}', [SiteController::class, 'update']);
        $router->put('/sites/current', [SiteController::class, 'updateCurrent']);
        $router->delete('/sites/{id}', [SiteController::class, 'delete']);

// Contact Info Routes
        $router->get('/sites/contact', [SiteController::class, 'getContactInfo']);
        $router->put('/sites/contact', [SiteController::class, 'updateContactInfo']);

// Social Media Routes
        $router->put('/sites/social', [SiteController::class, 'updateSocialMedia']);

// Branding Routes
        $router->post('/sites/logo', [SiteController::class, 'uploadLogo']);
        $router->post('/sites/favicon', [SiteController::class, 'uploadFavicon']);

// Settings Routes
        $router->put('/sites/settings', [SiteController::class, 'updateSettings']);

// Status Routes
        $router->put('/sites/{id}/status', [SiteController::class, 'toggleStatus']);

        // Region Sets
        $router->get('/region-sets', [RegionSetController::class, 'index']);
        $router->post('/region-sets', [RegionSetController::class, 'store']);
        $router->get('/region-sets/active', [RegionSetController::class, 'getActive']);
        $router->post('/region-sets/reorder', [RegionSetController::class, 'reorder']);
        $router->get('/region-sets/{id}', [RegionSetController::class, 'show']);
        $router->put('/region-sets/{id}', [RegionSetController::class, 'update']);
        $router->delete('/region-sets/{id}', [RegionSetController::class, 'destroy']);
        $router->get('/region-sets/{id}/check-deletable', [RegionSetController::class, 'checkDeletable']);
        $router->get('/region-sets/{id}/alternatives', [RegionSetController::class, 'getAlternatives']);
        $router->post('/region-sets/{id}/duplicate', [RegionSetController::class, 'duplicate']);

// Territories
        $router->get('/territories', [TerritoryController::class, 'index']);
        $router->post('/territories', [TerritoryController::class, 'store']);
        $router->get('/territories/active', [TerritoryController::class, 'getActive']);
        $router->post('/territories/reorder', [TerritoryController::class, 'reorder']);
        $router->post('/territories/bulk-update-region-set', [TerritoryController::class, 'bulkUpdateRegionSet']);
        $router->get('/territories/by-region-set/{regionSetId}', [TerritoryController::class, 'getByRegionSet']);
        $router->get('/territories/{id}', [TerritoryController::class, 'show']);
        $router->put('/territories/{id}', [TerritoryController::class, 'update']);
        $router->delete('/territories/{id}', [TerritoryController::class, 'destroy']);
        $router->get('/territories/{id}/check-deletable', [TerritoryController::class, 'checkDeletable']);
        $router->get('/territories/{id}/alternatives', [TerritoryController::class, 'getAlternatives']);

        $router->post('/territories/bulk-delete', [TerritoryController::class, 'bulkDelete']);
        $router->post('/territories/bulk-activate', [TerritoryController::class, 'bulkActivate']);
        $router->post('/territories/bulk-deactivate', [TerritoryController::class, 'bulkDeactivate']);

        $router->get('/region-sets/{id}/pages', [RegionSetController::class, 'getPages']);
        $router->get('/region-sets/{id}/search-pages', [RegionSetController::class, 'searchAvailablePages']);
        $router->post('/region-sets/{id}/assign-pages', [RegionSetController::class, 'assignPages']);
        $router->post('/region-sets/{id}/unassign-pages', [RegionSetController::class, 'unassignPages']);
        $router->post('/region-sets/bulk-delete', [RegionSetController::class, 'bulkDelete']);
        $router->post('/region-sets/bulk-activate', [RegionSetController::class, 'bulkActivate']);
        $router->post('/region-sets/bulk-deactivate', [RegionSetController::class, 'bulkDeactivate']);

// Territory Pages
        $router->get('/territories/{id}/pages', [TerritoryController::class, 'getPages']);
        $router->get('/territories/{id}/search-pages', [TerritoryController::class, 'searchAvailablePages']);
        $router->post('/territories/{id}/assign-pages', [TerritoryController::class, 'assignPages']);
        $router->post('/territories/{id}/unassign-pages', [TerritoryController::class, 'unassignPages']);

        // CMS Page Collaborators & Brief
        $router->get('/pages/{pageId}/collaborators', [PageController::class, 'getCollaborators']);
        $router->post('/pages/{pageId}/collaborators', [PageController::class, 'addCollaborator']);
        $router->delete('/pages/{pageId}/collaborators/{collaboratorId}', [PageController::class, 'removeCollaborator']);
        $router->get('/pages/{pageId}/brief', [PageController::class, 'getBrief']);

        // Search
        $router->get('/search/pages', SearchController::class, 'pages');
        $router->get('/search/categories', SearchController::class, 'categories');

        $router->get('/vouchers', VoucherController::class, 'index');
        $router->post('/vouchers', VoucherController::class, 'store');
        $router->get('/vouchers/active', [VoucherController::class, 'active']);
        $router->get('/vouchers/{id}', VoucherController::class, 'show');
        $router->put('/vouchers/{id}', VoucherController::class, 'update');
        $router->delete('/vouchers/{id}', VoucherController::class, 'destroy');
        $router->get('/vouchers/{id}/check-delete', VoucherController::class, 'checkDelete');
        $router->get('/vouchers/{id}/alternatives', VoucherController::class, 'alternatives');
        $router->post('/vouchers/{id}/duplicate', VoucherController::class, 'duplicate');
        $router->post('/vouchers/validate', [VoucherController::class, 'validate']);
        $router->post('/vouchers/{id}/apply', VoucherController::class, 'apply');
        $router->get('/vouchers/{id}/redemptions', VoucherController::class, 'redemptions');
        $router->post('/vouchers/bulk-status', [VoucherController::class, 'bulkUpdateStatus']);
        $router->post('/vouchers/bulk-delete', [VoucherController::class, 'bulkDelete']);

        $router->get(
            '/subscription-vouchers',
            [SubscriptionVoucherController::class, 'index'],
        );

        $router->post(
            '/subscription-vouchers',
            [SubscriptionVoucherController::class, 'store'],
        );

        // ⚠️ Static segment /deletable must be registered BEFORE /{id} so the router
        // resolves GET /subscription-vouchers/123/deletable to checkDelete, not show.
        $router->get(
            '/subscription-vouchers/{id}/deletable',
            [SubscriptionVoucherController::class, 'checkDelete'],
        );

        $router->get(
            '/subscription-vouchers/{id}',
            [SubscriptionVoucherController::class, 'show'],
        );

        $router->put(
            '/subscription-vouchers/{id}',
            [SubscriptionVoucherController::class, 'update'],
        );

        $router->delete(
            '/subscription-vouchers/{id}',
            [SubscriptionVoucherController::class, 'destroy'],
        );

        $router->post('/newsletter/signup', NewsletterController::class, 'signup');
        $router->post('/newsletter/confirm', NewsletterController::class, 'confirm');
        $router->post('/newsletter/unsubscribe', NewsletterController::class, 'unsubscribe');
        $router->get('/newsletter/subscribers', NewsletterController::class, 'getSubscribers');

        $router->get('/newsletters', [NewsletterController::class, 'index']);
        $router->post('/newsletters', [NewsletterController::class, 'create']);
        $router->get('/newsletters/{id}/subscribers', [NewsletterController::class, 'getNewsletterSubscribers']);
        $router->post('/newsletters/{id}/send', [NewsletterController::class, 'send']);
        $router->post('/newsletters/{id}/pause', [NewsletterController::class, 'togglePause']);
        $router->delete('/newsletters/{id}', [NewsletterController::class, 'delete']);
        $router->put('/newsletters/{id}', [NewsletterController::class, 'update']);
        $router->get('/newsletters/{id}', [NewsletterController::class, 'show']);
        $router->get('/newsletters/statistics', [NewsletterController::class, 'statistics']);
        $router->post('/newsletters/{newsletterId}/issues', [NewsletterIssueController::class, 'store']);
        $router->post('/newsletters/{newsletterId}/issues/{issueId}/send', [NewsletterIssueController::class, 'send']);
        $router->get('/newsletters/{newsletterId}/issues', [NewsletterIssueController::class, 'index']);
        $router->get('/newsletters/{newsletterId}/issues/{issueId}', [NewsletterIssueController::class, 'show']);
        $router->post('/newsletters/{newsletterId}/issues/{issueId}/revert', [NewsletterIssueController::class, 'revert']);
        $router->post('/newsletter-issues/{issueId}/send', [NewsletterIssueController::class, 'manualSend']);

        // newsletter stats
        $router->get('/newsletters/statistics/clicks', [NewsletterController::class, 'getClickDetails']);
        $router->get('/newsletters/statistics/failed-sends', [NewsletterController::class, 'getFailedSendDetails']);
        $router->get('/newsletters/statistics/unique-clickers', [NewsletterController::class, 'getUniqueClickerDetails']);
        $router->get('/newsletters/statistics/sends', [NewsletterController::class, 'getSendDetails']);
        $router->get('/newsletters/statistics/recipients', [NewsletterController::class, 'getRecipientDetails']);

        $router->get('/newsletters/{newsletterId}/schedules',
            [NewsletterScheduleController::class, 'index']
        );

        // Creation schedule
        $router->post('/newsletters/{newsletterId}/schedules/creation',
            [NewsletterScheduleController::class, 'storeCreation']
        );
        $router->put('/newsletters/{newsletterId}/schedules/creation/{scheduleId}',
            [NewsletterScheduleController::class, 'updateCreation']
        );
        $router->delete('/newsletters/{newsletterId}/schedules/creation/{scheduleId}',
            [NewsletterScheduleController::class, 'destroyCreation']
        );

        // Send schedule
        $router->post('/newsletters/{newsletterId}/schedules/send',
            [NewsletterScheduleController::class, 'storeSend']
        );
        $router->put('/newsletters/{newsletterId}/schedules/send/{scheduleId}',
            [NewsletterScheduleController::class, 'updateSend']
        );
        $router->delete('/newsletters/{newsletterId}/schedules/send/{scheduleId}',
            [NewsletterScheduleController::class, 'destroySend']
        );

        $router->get('/members/{memberId}/addresses', [AddressController::class, 'getMemberAddresses']);

        $router->get('/pages/like-status/{pageId}', [PageLikeController::class, 'status']);

// Member routes for viewing history and liked pages
//        $router->get('/member/reading-history', [MemberReadingHistoryController::class, 'index']);
//        $router->get('/member/liked-pages', [MemberLikedPagesController::class, 'index']);

        $router->get('/addresses', [AddressController::class, 'index']);
        $router->post('/addresses', [AddressController::class, 'store']);
        $router->put('/addresses/{id}', [AddressController::class, 'update']);
        $router->delete('/addresses/{id}', [AddressController::class, 'destroy']);
        $router->post('/addresses/{id}/set-default', [AddressController::class, 'setDefault']);
        $router->get('/members/{memberId}/addresses', [AddressController::class, 'getMemberAddresses']);

        // Issue Deliveries
        $router->get('/issue-deliveries', [IssueDeliveryController::class, 'index']);
        $router->post('/issue-deliveries', [IssueDeliveryController::class, 'store']);
        $router->get('/issue-deliveries/search', [IssueDeliveryController::class, 'index']);
        $router->get('/issue-deliveries/{id}', [IssueDeliveryController::class, 'show']);
        $router->post('/issue-deliveries/{id}', [IssueDeliveryController::class, 'update']);
        $router->put('/issue-deliveries/{id}', [IssueDeliveryController::class, 'update']);
        $router->delete('/issue-deliveries/{id}', [IssueDeliveryController::class, 'destroy']);
        $router->put('/issue-deliveries/{id}/status', [IssueDeliveryController::class, 'updateStatus']);

        // Subscription Plan Pricing
        $router->get('/subscription-plans/{planId}/pricing', [SubscriptionPlanPricingController::class, 'index']);
        $router->get('/subscription-plans/pricing', [SubscriptionPlanPricingController::class, 'index']);
        $router->post('/subscription-plans/{planId}/pricing', [SubscriptionPlanPricingController::class, 'store']);
        $router->put('/subscription-plans/{planId}/pricing/sort-order', [SubscriptionPlanPricingController::class, 'updateSortOrder']);
        $router->put('/subscription-plans/{planId}/pricing/{pricingId}', [SubscriptionPlanPricingController::class, 'update']);
        $router->delete('/subscription-plans/{planId}/pricing/{pricingId}', [SubscriptionPlanPricingController::class, 'destroy']);
        $router->post('/subscription-plans/{planId}/pricing/{pricingId}/set-default', [SubscriptionPlanPricingController::class, 'setDefault']);
        $router->post('/subscription-plans/{planId}/pricing/{pricingId}/toggle-active', [SubscriptionPlanPricingController::class, 'toggleActive']);

        $router->post('/subscription-plans/{planId}/segments/assign', [PlanSegmentApiController::class, 'assign']);
        $router->delete('/subscription-plans/{planId}/segments/{segmentId}', [PlanSegmentApiController::class, 'remove']);
        $router->get('/segments/{segmentId}/subscription-plans', [PlanSegmentApiController::class, 'plansForSegment']);
        $router->post('/segments/{segmentId}/subscription-plans/assign', [PlanSegmentApiController::class, 'assignPlansToSegment']);
        $router->post('/segments/{segmentId}/preview', [SegmentPreviewApiController::class, 'preview']);
        $router->post('/subscriptions/{subscriptionId}/segment/assign', [SubscriptionSegmentOverrideApiController::class, 'assign']);
        $router->get('/admin/segment-fields', [SegmentFieldsApiController::class, '__invoke']);
        $router->get('/segments/subscription', [SubscriptionSegmentsApiController::class, 'index']);

        $router->get('/subscriptions/{subscriptionId}/segment', [SubscriptionSegmentApiController::class, 'show']);

        $router->post('/newsletters/{newsletterId}/branding', [NewsletterBrandingController::class, 'save']);
        $router->get('/newsletters/{newsletterId}/branding', [NewsletterBrandingController::class, 'show']);
        $router->get('/newsletters/{newsletterId}/branding/versions', [NewsletterBrandingController::class, 'versions']);


        $router->group(['prefix' => '/newsletter-layouts'], function ($router) {

            // Layout CRUD
            $router->get('/', [NewsletterLayoutController::class, 'index']);
            $router->get('/system', [NewsletterLayoutController::class, 'systemLayouts']);
            $router->post('/', [NewsletterLayoutController::class, 'store']);
            $router->post('/{id}/clone', [NewsletterLayoutController::class, 'clone']);
            $router->delete('/{id}', [NewsletterLayoutController::class, 'delete']);

            // Layout Versions
            $router->get('/{id}/versions', [NewsletterLayoutController::class, 'versions']);
            $router->post('/{id}/versions', [NewsletterLayoutController::class, 'addVersion']);
        });

        $router->put(
            'newsletter-layout-versions/{versionId}/state',
            [NewsletterLayoutController::class, 'transitionState']
        );

        $router->post(
            'newsletter-layouts/migration-report',
            [NewsletterLayoutController::class, 'migrationReport']
        );

        // Gift Promotions
        $router->get('/gift-promotions', [GiftPromotionController::class, 'index']);
        $router->post('/gift-promotions', [GiftPromotionController::class, 'store']);
        $router->put('/gift-promotions/{id}', [GiftPromotionController::class, 'update']);
        $router->post('/gift-promotions/{id}/toggle-active', [GiftPromotionController::class, 'toggleActive']);
        $router->get('/gift-promotions/{id}/exclusions', [GiftPromotionController::class, 'exclusions']);

    });

    $router->post('/sites', [SiteController::class, 'create']);

});

$router->post('/api/{site}/vouchers/validate', VoucherController::class, 'validate');
$router->post('/api/{site}/vouchers/{id}/apply', VoucherController::class, 'apply');

$router->post('/api/{site}/newsletter/web/signup', [NewsletterController::class, 'signup']);


$router->get('/api/pages/{pageId}/custom-fields', CustomFieldDefinitionController::class, 'getCustomFields');

$router->put('/api/pages/{pageId}/custom-fields', CustomFieldDefinitionController::class, 'updateCustomFields');


// Blocks API
$router->get('/api/blocks/{id}', BlockController::class, 'show');
$router->put('/api/blocks/{id}', BlockController::class, 'update');
$router->delete('/api/blocks/{id}', BlockController::class, 'destroy');
$router->get('/api/blocks/type/{type}', BlockController::class, 'getByType');
$router->get('/api/search-properties', EstateWebsiteController::class, 'search');


// Menu items
$router->get('/api/menu-items', MenuItemController::class, 'index');
$router->post('/api/menu-items', MenuItemController::class, 'store');
$router->get('/api/menu-items/{id}', MenuItemController::class, 'show');
$router->put('/api/menu-items/{id}', MenuItemController::class, 'update');
$router->delete('/api/menu-items/{id}', MenuItemController::class, 'destroy');
$router->post('/api/menu-items/reorder', MenuItemController::class, 'reorder');


// Author public view route
$router->get('/authors/{slug}', 'AuthorViewController@show');

//Auth
$router->post('/api/{site}/auth/login', AuthController::class, 'login');
$router->get('/api/sites', SiteController::class, 'index');

$router->get('/api/{site}/cart', CartController::class, 'index');
$router->post('/api/{site}/cart/add', CartController::class, 'add');
$router->put('/api/{site}/cart/update/{id}', CartController::class, 'update');
$router->delete('/api/{site}/cart/remove/{id}', CartController::class, 'remove');
$router->post('/api/{site}/cart/clear', CartController::class, 'clear');

$router->get('/api/{site}/wishlist', WishlistController::class, 'index');
$router->post('/api/{site}/wishlist/add', WishlistController::class, 'add');
$router->delete('/api/{site}/wishlist/remove/{productId}', WishlistController::class, 'remove');


// Product routes
$router->get('/api/{site}/product-list/search', [ProductListController::class, 'search']);


//reviews
$router->get('/api/{site}/products/{productId}/reviews', ReviewController::class, 'index');
$router->post('/api/{site}/products/{productId}/reviews', [ReviewController::class, 'store']);
$router->post('/api/{site}/plans/{planId}/reviews', [ReviewController::class, 'storePlanReview']);
$router->get('/api/{site}/plans/{planId}/reviews', [ReviewController::class, 'getPlanReview']);
$router->put('/api/{site}/reviews/{reviewId}', ReviewController::class, 'update');
$router->delete('/api/{site}/reviews/{reviewId}', ReviewController::class, 'destroy');
$router->post('/api/{site}/reviews/{reviewId}/helpful', ReviewController::class, 'markHelpful');
$router->get('/api/{site}/products/{productId}/reviews/statistics', ReviewController::class, 'statistics');
$router->get('/api/{site}/products/{productId}/reviews/can-review', ReviewController::class, 'canReview');

// Video routes
$router->get('/api/{site}/videos', VideoController::class, 'index');
$router->post('/api/{site}/videos', VideoController::class, 'upload');
$router->get('/api/{site}/videos/{id}', VideoController::class, 'show');
$router->delete('/api/{site}/videos/{id}', VideoController::class, 'delete');

$router->post('/api/preview', [PreviewController::class, 'preview']);

//comments
$router->post('/comments', [CommentController::class, 'store']);
$router->put('/comments/{commentId}/moderate', [CommentController::class, 'moderate']);
$router->get('/pages/{pageId}/comments', [CommentController::class, 'index']);
$router->delete('/comments/{commentId}', [CommentController::class, 'destroy']);

// Deals routes
$router->post('/api/deals/refresh', [DealsController::class, 'refresh']);
$router->get('/api/deals/carousel', [DealsController::class, 'carousel']);
$router->get('/api/{site}/deals/filtered', [DealsController::class, 'filtered']);

// Price alerts
$router->post('/api/price-alerts', [DealsController::class, 'createPriceAlert']);
$router->post('/api/deal-alerts/subscribe', [DealsController::class, 'subscribeDealAlert']);

$router->post('/{site}/api/subscription-modal/mark-shown', [SubscriptionModalController::class, 'markShown']);

$router->get('/api/{site}/member/payment-methods', [SavedPaymentMethodsController::class, 'list']);
$router->put('/api/{site}/cart/{id}/update-start-date', [CartController::class, 'updateStartDate']);
$router->post('/api/{site}/vouchers/remove-voucher', VoucherController::class, 'removeVoucher');

$router->post('/api/{site}/merchants/{merchantId}/import', [MerchantImportController::class, 'import']);

//reccommendations
$router->get('/api/{site}/recommendations/products', [RecommendationController::class, 'products']);

$router->get('/api/{site}/member/current-address', [MemberAddressApiController::class, 'getCurrentAddress']);
$router->get('/api/{site}/address-lookup', [AddressController::class, 'lookup']);

$router->group(['prefix' => '/api/{site}/open-collab'], function () use ($router) {

    // Epic 2: Onboarding API (Targets OnboardingController)
    $router->group(['prefix' => 'onboarding', 'middleware' => [EnsureOnboardingNotExpired::class]], function () use ($router) {
        $router->get('/status', [OnboardingController::class, 'status']);
        $router->post('/profile', [OnboardingController::class, 'storeProfile']);
        $router->post('/steps/profile/complete', [OnboardingController::class, 'completeProfileStep']);
        $router->post('/payment', [OnboardingController::class, 'storePaymentDetails']);
        $router->get('/payment-methods', [OnboardingController::class, 'paymentMethods']);
        $router->post('/payment-methods/{paymentMethodId}/default', [OnboardingController::class, 'setDefaultPaymentMethod']);
        $router->delete('/payment-methods/{paymentMethodId}', [OnboardingController::class, 'removePaymentMethod']);
        $router->post('/steps/payment/complete', [OnboardingController::class, 'completePaymentStep']);
        $router->post('/steps/kyc-verification/complete', [OnboardingController::class, 'completeKycVerificationStep']);
        $router->get('/contract', [OnboardingController::class, 'getContract']);
        $router->post('/contract', [OnboardingController::class, 'signContract']);
        $router->post('/guidelines', [OnboardingController::class, 'acknowledgeGuidelines']);
        $router->post('/age-verification', [OnboardingController::class, 'updateAgeVerification']);
    });

    $router->group(['prefix' => 'notifications'], function () use ($router) {
        $router->get('', [NotificationController::class, 'index']);
        $router->get('/unread-count', [NotificationController::class, 'unreadCount']);
        $router->post('/read', [NotificationController::class, 'markAsRead']);
        $router->post('/{notification}/read', [NotificationController::class, 'markAsReadById']);
        $router->post('/read-all', [NotificationController::class, 'markAllAsRead']);
        $router->get('/preferences', [ContributorNotificationPreferenceController::class, 'index']);
        $router->post('/preferences', [ContributorNotificationPreferenceController::class, 'update']);
        $router->post('/preferences/batch', [ContributorNotificationPreferenceController::class, 'updateBatch']
        );
    });

    $router->get('/admin/users/search', [SiteSettingsController::class, 'searchUsers']);
    $router->post('/admin/sites/users', [SiteSettingsController::class, 'assignUser']);
    $router->get('/admin/sites/users', [SiteSettingsController::class, 'assignedUsers']);
    $router->post('/admin/sites/settings', [SiteSettingsController::class, 'update']);
    $router->delete('/admin/sites/users/{userId}', [SiteSettingsController::class, 'removeUser']);
    $router->get('/admin/contracts', [AdminContractController::class, 'index']);
    $router->get('/admin/contracts/latest', [AdminContractController::class, 'latest']);
    $router->post('/admin/contracts', [AdminContractController::class, 'store']);
    $router->get('/admin/contracts/{id}', [AdminContractController::class, 'show']);
    $router->put('/admin/contracts/{id}', [AdminContractController::class, 'update']);
    $router->delete('/admin/contracts/{id}', [AdminContractController::class, 'destroy']);

    $router->get('/admin/guidelines', [AdminGuidelinesController::class, 'index']);
    $router->get('/admin/guidelines/latest', [AdminGuidelinesController::class, 'latest']);
    $router->post('/admin/guidelines', [AdminGuidelinesController::class, 'store']);
    $router->get('/admin/guidelines/{id}', [AdminGuidelinesController::class, 'show']);
    $router->put('/admin/guidelines/{id}', [AdminGuidelinesController::class, 'update']);
    $router->delete('/admin/guidelines/{id}', [AdminGuidelinesController::class, 'destroy']);

    // Epic 3: Article Management (Targets ContributorPageController)
    //$router->apiResource('pages', ContributorPageController::class);

    // Epic 4: Payments (Targets ArticlePaymentController)
    $router->post('/pages/{pageId}/comments', [ArticleCommentController::class, 'store']);
    $router->get('/pages/{pageId}/comments', [ArticleCommentController::class, 'index']);
    $router->post('/pages/{pageId}/comments/{id}/reply', [ArticleCommentController::class, 'reply']);
    $router->delete('/comments/{id}', [ArticleCommentController::class, 'destroy']);

    $router->get('/pages/{pageId}/history', [ArticleHistoryController::class, 'index']);
    $router->post('/pages/{pageId}/history/{historyId}/restore', [ArticleHistoryController::class, 'restore']);

    $router->post('/payments/{id}/retry', [PaymentRetryController::class, 'retry']);

    $router->post('/pages/{pageId}/purchase', [ArticlePaymentController::class, 'initiate']);

    $router->post('/auth/logout', [ContributorAuthController::class, 'logout']);
    $router->post('/auth/login', [ContributorAuthController::class, 'login']);

    // Activity Feed (Targets ActivityFeedController)
    $router->get('/activity', [ActivityFeedController::class, 'index']);
    $router->get('/activity/site', [ActivityFeedController::class, 'siteWide']); // Admin only

    // Invitations (Targets InvitationController)
    $router->post('/invitations', [InvitationController::class, 'store']);
    $router->post('/invitations/resend', [ResendInvitationController::class, 'resend']);

    // POST /api/{site}/open-collab/pages
    // Triggers: ContributorPageController@store
    $router->post('/pages', [ContributorPageController::class, 'store']);
    $router->post('/pages/{id}/submit', [ArticleApprovalController::class, 'submit']);

    // PUT /api/{site}/open-collab/pages/{id}
    // Triggers: ContributorPageController@update
    $router->put('/pages/{id}', [ContributorPageController::class, 'update']);

    $router->post('/admin/contributors/{id}/role', [AdminContributorController::class, 'updateRole']);


    // DELETE /api/{site}/open-collab/pages/{id}
    // Triggers: ContributorPageController@destroy
    $router->delete('/pages/{id}', [ContributorPageController::class, 'destroy']);

    $router->post('/invitations', [InvitationController::class, 'store']);

    $router->post('/disputes', [EarningsDisputeController::class, 'store']);
    $router->get('/disputes', [EarningsDisputeController::class, 'index']);
    $router->get('/admin/disputes', [EarningsDisputeController::class, 'adminIndex']);
    $router->post('/admin/disputes/{id}/reject', [EarningsDisputeController::class, 'reject']);
    $router->post('/admin/disputes/{id}/resolve', [EarningsDisputeController::class, 'resolve']);

    $router->get('/admin/violations', [ViolationController::class, 'siteIndex']);
    $router->get('/admin/contributors/{userId}/violations', [ViolationController::class, 'index']);
    $router->post('/admin/contributors/{userId}/violations', [ViolationController::class, 'store']);
    $router->post('/admin/violations/{id}/resolve', [ViolationController::class, 'resolve']);
    $router->post('/admin/contracts/{id}/publish', [AdminContractController::class, 'publish']);

    $router->post('/admin/payment-terms', [AdminPaymentTermsController::class, 'save']);

    $router->post('/invitations/{token}/accept', [InvitationController::class, 'accept']);

    $router->post(
        '/contributor-requests',
        [ContributorRequestController::class, 'store']
    );

});

$router->get('/api/{site}/open-collab/payouts/{id}/statement', [PayoutStatementController::class, 'download']);
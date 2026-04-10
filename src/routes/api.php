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
use App\Controllers\Cms\BriefController;
use App\Controllers\Cms\Briefs\BriefPresetController;
use App\Controllers\Cms\CampaignController;
use App\Controllers\Cms\CategoryController;
use App\Controllers\Cms\CustomFieldDefinitionController;
use App\Controllers\Cms\EmailThemeController;
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
use App\Controllers\Crm\CrmMemberController;
use App\Controllers\Front\CommentController;
use App\Controllers\Front\EstateWebsiteController;
use App\Controllers\Front\PageLikeController;
use App\Controllers\Front\WishlistController;
use App\Controllers\MemberController;
use App\Controllers\Members\MemberAddressController;
use App\Controllers\Members\MemberPaymentMethodsController;
use App\Controllers\Members\Subscriptions\AdminSubscriptionPremiumAccessController;
use App\Controllers\MenuController;
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
use App\Controllers\OpenCollab\ArticlePaymentController;
use App\Controllers\OpenCollab\ContributorDashboardController;
use App\Controllers\OpenCollab\ContributorPageController;
use App\Controllers\OpenCollab\InvitationController;
use App\Controllers\OpenCollab\OnboardingController;
use App\Controllers\OpenCollab\StripeWebhookController;
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
use App\Controllers\Subscription\SubscriptionController;
use App\Controllers\Subscription\SubscriptionModalController;
use App\Controllers\Subscription\SubscriptionPlanPricingController;
use App\Controllers\Subscription\SubscriptionPlanSubscriberController;
use App\Controllers\Subscription\WorkflowRunController;
use App\Controllers\Vouchers\VoucherController;
use App\Framework\Authorization\AuthenticateWithToken;

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
$router->post('/api/{site}/internal/workflow/run', [\App\Controllers\WorkflowController::class, 'run']);
$router->get('/api/{site}/internal/workflow/logs', [\App\Controllers\WorkflowController::class, 'logs']);
$router->get('/api/{site}/internal/workflow/classes', [\App\Controllers\WorkflowController::class, 'classes']);
$router->get('/api/{site}/internal/workflow/listen', [\App\Controllers\WorkflowController::class, 'listen']);

$router->post(
    '/api/{site}/open-collab/pages/{pageId}/purchase',
    [ArticlePaymentController::class, 'initiate']
);

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


$router->group(['prefix' => 'api', 'middleware' => AuthenticateWithToken::class], function ($router) {

    // Pages API
    $router->group(['prefix' => '{siteName}'], function ($router) {
        $router->get('/contact-info', SiteController::class, 'getContactInfo');

        $router->post(
            '/open-collab/invitations',
            [InvitationController::class, 'store']
        );

        $router->get(
            '/open-collab/dashboard',
            [ContributorDashboardController::class, 'show']
        );

        // Contributor pages (articles)
        $router->get(
            '/open-collab/pages',
            [ContributorPageController::class, 'index']
        );

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

        // crm
        $router->get('/crm/members', [CrmMemberController::class, 'index']);
        $router->get('/crm/members/{id}', [CrmMemberController::class, 'show']);
        $router->post('/crm/members', [CrmMemberController::class, 'store']);
        $router->put('/crm/members/{id}', [CrmMemberController::class, 'update']);
        $router->delete('/crm/members/{id}', [CrmMemberController::class, 'destroy']);

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
        $router->get('/briefs/templates', [BriefController::class, 'getTemplates']);;
        $router->post('/briefs/templates/{templateId}/create', [BriefController::class, 'createFromTemplate']);
        $router->post('/briefs/{id}/save-template', [BriefController::class, 'saveAsTemplate']);;

// Collaborators
        $router->get('/briefs/{id}/collaborators', [BriefController::class, 'getCollaborators']);
        $router->post('/briefs/{id}/collaborators', [BriefController::class, 'addCollaborator']);
        $router->delete('/briefs/{id}/collaborators/{collaboratorId}', [BriefController::class, 'removeCollaborator']);
        $router->put('/briefs/{id}/collaborators/{collaboratorId}', [BriefController::class, 'updateCollaborator']);

// Tasks
        $router->get('/briefs/{id}/tasks', [BriefController::class, 'getTasks']);
        $router->post('/briefs/{id}/tasks', [BriefController::class, 'createTask']);;
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

        $router->get('/pages', PageController::class, 'index');
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
        $router->get('/pages/calendar', [PageController::class, 'getCalendarPages']);;
        $router->get('/featured-pages', PageController::class, 'getFeaturedPages');
        $router->put('/pages/{id}/schedule', [PageController::class, 'updateSchedule']);

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

        $router->get('/member/activity/stats', 'Api\MemberActivityApiController@stats');
        $router->get('/member/activity/trends', 'Api\MemberActivityApiController@trends');
        $router->get('/member/badges/progress', 'Api\MemberActivityApiController@badgeProgress');

        $router->get('/email-themes', [EmailThemeController::class, 'index']);
        $router->get('/email-themes/active', [EmailThemeController::class, 'getActive']);
        $router->post('/email-themes', [EmailThemeController::class, 'store']);
        $router->get('/email-themes/{id}', [EmailThemeController::class, 'show']);
        $router->put('/email-themes/{id}', [EmailThemeController::class, 'update']);;
        $router->delete('/email-themes/{id}', [EmailThemeController::class, 'destroy']);;
        $router->post('/email-themes/{id}/set-default', [EmailThemeController::class, 'setDefault']);
        $router->get('/email-themes/{id}/alternatives', [EmailThemeController::class, 'alternatives']);
        $router->post('/email-themes/{id}/duplicate', [EmailThemeController::class, 'duplicate']);
        $router->post('/email-themes/bulk-delete', [EmailThemeController::class, 'bulkDelete']);;

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

        $router->get('/pages/{pageId}/custom-fields/grouped', CustomFieldDefinitionController::class, 'getCustomFieldsGrouped');;

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
        $router->post('/orders/{id}/refund', OrderController::class, 'refund');
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

        $router->get('/specification-groups', [ProductController::class, 'specificationGroups']);;


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

         $router->post('/newsletter/signup', NewsletterController::class, 'signup');
         $router->post('/newsletter/confirm', NewsletterController::class, 'confirm');
         $router->post('/newsletter/unsubscribe', NewsletterController::class, 'unsubscribe');
         $router->get('/newsletter/subscribers', NewsletterController::class, 'getSubscribers');

        $router->get('/newsletters', [NewsletterController::class, 'index']);
        $router->post('/newsletters', [NewsletterController::class, 'create']);
        $router->get('/newsletters/{id}/subscribers', [NewsletterController::class, 'getNewsletterSubscribers']);
        $router->post('/newsletters/{id}/send', [NewsletterController::class, 'send']);
        $router->post('/newsletters/{id}/pause', [NewsletterController::class, 'togglePause']);
        $router->delete('/newsletters/{id}', [NewsletterController::class, 'delete']);;
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

        $router->post('/pages/like/{pageId}', [PageLikeController::class, 'toggle']);
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

$router->post('/api/{siteName}/vouchers/validate', VoucherController::class, 'validate');
$router->post('/api/{siteName}/vouchers/{id}/apply', VoucherController::class, 'apply');

$router->post('/api/{siteName}/newsletter/web/signup', [NewsletterController::class, 'signup']);


$router->get('/api/pages/{pageId}/custom-fields', CustomFieldDefinitionController::class, 'getCustomFields');

$router->put('/api/pages/{pageId}/custom-fields', CustomFieldDefinitionController::class, 'updateCustomFields');;


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
$router->post('/api/{siteName}/auth/login', AuthController::class, 'login');
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
$router->get('/api/{siteName}/deals/filtered', [DealsController::class, 'filtered']);

// Price alerts
$router->post('/api/price-alerts', [DealsController::class, 'createPriceAlert']);
$router->post('/api/deal-alerts/subscribe', [DealsController::class, 'subscribeDealAlert']);

$router->post('/{site}/api/subscription-modal/mark-shown', [SubscriptionModalController::class, 'markShown']);

$router->get('/api/{site}/member/payment-methods', [MemberPaymentMethodsController::class, 'getPaymentMethodsForMember']);
$router->put('/api/{site}/cart/{id}/update-start-date', [CartController::class, 'updateStartDate']);
$router->post('/api/{site}/vouchers/remove-voucher', VoucherController::class, 'removeVoucher');

$router->post('/api/{site}/merchants/{merchantId}/import', [MerchantImportController::class, 'import']);

//reccommendations
$router->get('/api/{siteName}/recommendations/products', [RecommendationController::class, 'products']);

$router->get('/api/{site}/member/current-address', [MemberAddressController::class, 'getCurrentAddress']);
$router->get('/api/{site}/address-lookup', [AddressController::class, 'lookup']);

$router->group(['prefix' => '/api/{site}/open-collab'], function () use ($router) {

    // Epic 2: Onboarding API (Targets OnboardingController)
    $router->group(['prefix' => 'onboarding'], function () use ($router) {
        $router->get('/status', [OnboardingController::class, 'status']);
        $router->post('/profile', [OnboardingController::class, 'storeProfile']);
        $router->post('/payment', [OnboardingController::class, 'storePaymentDetails']);
        $router->post('/contract', [OnboardingController::class, 'signContract']);
        $router->post('/guidelines', [OnboardingController::class, 'acknowledgeGuidelines']);
    });

    // Epic 3: Article Management (Targets ContributorPageController)
    //$router->apiResource('pages', ContributorPageController::class);

    // Epic 4: Payments (Targets ArticlePaymentController)
    $router->post('/pages/{id}/purchase', [ArticlePaymentController::class, 'initiate']);

    // Activity Feed (Targets ActivityFeedController)
    $router->get('/activity', [ActivityFeedController::class, 'index']);
    $router->get('/activity/site', [ActivityFeedController::class, 'siteWide']); // Admin only

    // Invitations (Targets InvitationController)
    $router->post('/invitations', [InvitationController::class, 'store']);

    // POST /api/{site}/open-collab/pages 
    // Triggers: ContributorPageController@store
    $router->post('/pages', [ContributorPageController::class, 'store']);

    // PUT /api/{site}/open-collab/pages/{id}
    // Triggers: ContributorPageController@update
    $router->put('/pages/{id}', [ContributorPageController::class, 'update']);

    // DELETE /api/{site}/open-collab/pages/{id}
    // Triggers: ContributorPageController@destroy
    $router->delete('/pages/{id}', [ContributorPageController::class, 'destroy']);

    $router->post('/invitations', [\App\Controllers\OpenCollab\InvitationController::class, 'store']);

    $router->post('/invitations/{token}/accept', [InvitationController::class, 'accept']);
});


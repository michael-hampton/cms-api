<?php

namespace App;

use App\Console\SyncStripePlansCommand;
use App\Console\SyncStripePricesCommand;
use App\Contracts\ClockInterface;
use App\Enums\Subscriptions\LabelExportFormat;
use App\Enums\Subscriptions\SubscriptionType;
use App\Events\Alerts\OfferExpiryAlertDispatched;
use App\Events\ArticleGifting\GiftClaimedEvent;
use App\Events\ArticleGifting\GiftCreatedEvent;
use App\Events\Badges\BadgeEarnedEvent;
use App\Events\Badges\PointsAwardedEvent;
use App\Events\Boost\BoostActivatedEvent;
use App\Events\Boost\BoostCancelledEvent;
use App\Events\Boost\BoostCreatedEvent;
use App\Events\Boost\BoostExpiredEvent;
use App\Events\Boost\BoostLimitBreachedEvent;
use App\Events\Boost\BoostPausedEvent;
use App\Events\Boost\BoostResumedEvent;
use App\Events\Cms\ContentEditoriallyModified;
use App\Events\DatabaseEventSubscriber;
use App\Events\Cms\ContentApproved;
use App\Events\Cms\ContentHeld;
use App\Events\Cms\ContentRejected;
use App\Events\Cms\ContentSubmittedForApproval;
use App\Events\Members\CommentPostedByMember;
use App\Events\Members\MemberAddressImported;
use App\Events\Members\MemberDetailsChanged;
use App\Events\Members\MemberPostcodeUpdated;
use App\Events\Members\OrderCreatedByMember;
use App\Events\Members\PageLikedByMember;
use App\Events\Members\PageUnlikedByMember;
use App\Events\Members\PageViewedByMember;
use App\Events\Members\RewardClaimedByMember;
use App\Events\OpenCollab\ArticleApprovedEvent;
use App\Events\OpenCollab\ArticleNeedsChangesEvent;
use App\Events\OpenCollab\ArticlePurchasedEvent;
use App\Events\OpenCollab\ArticleRejectedEvent;
use App\Events\OpenCollab\ArticleSubmittedForReviewEvent;
use App\Events\OpenCollab\ChangesRequestedEvent;
use App\Events\OpenCollab\ContractPublishedEvent;
use App\Events\OpenCollab\DisputeRaisedEvent;
use App\Events\OpenCollab\DisputeResolvedEvent;
use App\Events\OpenCollab\GuidelinesVersionBumpedEvent;
use App\Events\OpenCollab\PayoutFailedEvent;
use App\Events\OpenCollab\PayoutProcessedEvent;
use App\Events\OpenCollab\ViolationRecordedEvent;
use App\Events\Orders\OrderCreatedEvent;
use App\Events\Products\AllProductFulfilmentsCreated;
use App\Events\Products\ProductFulfilmentCreated;
use App\Events\Products\ProductFulfilmentStalled;
use App\Events\Products\ProductViewedEvent;
use App\Events\Refunds\RefundCreated;
use App\Events\Rewards\MemberRewardApproved;
use App\Events\Stock\StockAllocated;
use App\Events\Stock\StockLow;
use App\Events\Stock\StockReleased;
use App\Events\Subscriptions\AllFulfilmentsCreated;
use App\Events\Subscriptions\InvoicePaymentFailed;
use App\Events\Subscriptions\InvoicePaymentSucceeded;
use App\Events\Subscriptions\InvoiceUpcoming;
use App\Events\Subscriptions\SubscriptionFirstIssueDelivered;
use App\Events\Subscriptions\IssueDeliveryDispatched;
use App\Events\Subscriptions\LabelRunFailed;
use App\Events\Subscriptions\LabelRunGenerated;
use App\Events\Subscriptions\PaymentFailed;
use App\Events\Subscriptions\PaymentRefunded;
use App\Events\Subscriptions\PaymentSucceeded;
use App\Events\Subscriptions\SubscriptionCancelled;
use App\Events\Subscriptions\SubscriptionCancelledByStripe;
use App\Events\Subscriptions\SubscriptionCreated;
use App\Events\Subscriptions\SubscriptionPaused;
use App\Events\Subscriptions\SubscriptionPricingChangeScheduled;
use App\Events\Subscriptions\SubscriptionReactivated;
use App\Events\Subscriptions\SubscriptionResumed;
use App\Events\Subscriptions\SubscriptionRenewedAndReplaced;
use App\Events\Subscriptions\SubscriptionProductChanged;
use App\Events\Subscriptions\SubscriptionSuspended;
use App\Events\Subscriptions\SubscriptionUnsuspended;
use App\Framework\Console\Artisan;
use App\Framework\Console\Commands\MakeControllerCommand;
use App\Framework\Console\Commands\MakeMigrationCommand;
use App\Framework\Console\Commands\MakeModelCommand;
use App\Framework\Console\Commands\MakeRepositoryCommand;
use App\Framework\Console\Commands\MigrateCommand;
use App\Framework\Console\Commands\MigrateRollbackCommand;
use App\Framework\Console\Commands\PruneCacheCommand;
use App\Framework\Console\Commands\QueueWorkCommand;
use App\Framework\Console\Commands\ScheduleRunCommand;
use App\Framework\Console\Commands\SeedCommand;
use App\Framework\Database\Database;
use App\Framework\Date;
use App\Framework\Events\EventDispatcher;
use App\Framework\FileUpload\FileSystem;
use App\Framework\FileUpload\FileSystemInterface;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Http\Router;
use App\Framework\Middleware\SessionMiddleware;
use App\Framework\Middleware\SiteDetectionMiddleware;
use App\Framework\Notifications\Channels\EmailChannel;
use App\Framework\Notifications\Channels\InAppNotificationChannel;
use App\Framework\Notifications\NotificationDispatcher;
use App\Framework\Queue\DatabaseQueueDriver;
use App\Framework\Queue\NullQueueDriver;
use App\Framework\Queue\QueueDriverInterface;
use App\Framework\Routing\RouteLoader;
use App\Framework\Storage\StoragePathResolver;
use App\Framework\Storage\StoragePathResolverInterface;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\DeliverIssueDeliveryJob;
use App\Listeners\Alerts\LogOfferExpiryAlertDispatched;
use App\Listeners\BadgeEarnedListener;
use App\Listeners\Boost\HandleOrderConversionAttribution;
use App\Listeners\Boost\SendBoostActivatedNotification;
use App\Listeners\Boost\SendBoostCancelledNotification;
use App\Listeners\Boost\SendBoostCreatedNotification;
use App\Listeners\Boost\SendBoostExpiredNotification;
use App\Listeners\Boost\SendBoostLimitBreachedNotification;
use App\Listeners\Boost\SendBoostPausedNotification;
use App\Listeners\Boost\SendBoostResumedNotification;
use App\Listeners\Cms\SendContentWorkflowNotification;
use App\Listeners\GiftClaimedListener;
use App\Listeners\GiftCreatedListener;
use App\Listeners\Members\MemberPostcodeUpdatedListener;
use App\Listeners\Members\RecordMemberEngagementMetric;
use App\Listeners\Members\SendAccountActivationEmailListener;
use App\Listeners\Members\SyncMemberToStripeJob;
use App\Listeners\Members\SyncMemberToStripeListener;
use App\Listeners\OpenCollab\InvalidateContributorOnboardingListener;
use App\Listeners\OpenCollab\NotifyContributorOfRequestedChangesListener;
use App\Listeners\OpenCollab\RecordSaleToEarningsLedger;
use App\Listeners\OpenCollab\SendArticleApprovedNotification;
use App\Listeners\OpenCollab\SendArticleNeedsChangesNotification;
use App\Listeners\OpenCollab\SendArticleRejectedNotification;
use App\Listeners\OpenCollab\SendContractPublishedNotification;
use App\Listeners\OpenCollab\SendArticleSentForReviewNotification;
use App\Listeners\OpenCollab\SendDisputeRaisedNotification;
use App\Listeners\OpenCollab\SendDisputeResolvedNotification;
use App\Listeners\OpenCollab\SendGuidelinesUpdatedNotification;
use App\Listeners\OpenCollab\SendPayoutFailedNotification;
use App\Listeners\OpenCollab\SendPayoutProcessedNotification;
use App\Listeners\OpenCollab\SendViolationRecordedNotification;
use App\Listeners\Orders\SendOrderConfirmationListener;
use App\Listeners\PointsAwardedListener;
use App\Listeners\Products\AllProductFulfilmentsCreatedListener;
use App\Listeners\Products\NotifyOpsOfStalledProductFulfilmentListener;
use App\Listeners\Products\ProductFulfilmentCreatedListener;
use App\Listeners\Products\TrackProductViewListener;
use App\Listeners\Refunds\LogRefundHistory;
use App\Listeners\Refunds\SendRefundNotification;
use App\Listeners\Rewards\ApproveProductLinkedRewardsListener;
use App\Listeners\Rewards\NotifyMemberOnRewardApproval;
use App\Listeners\Stock\StockAllocatedAnalyticsListener;
use App\Listeners\Stock\StockConfirmedAnalyticsListener;
use App\Listeners\Stock\StockLowAlertListener;
use App\Listeners\Subscriptions\AllFulfilmentsCreatedListener;
use App\Listeners\Subscriptions\IssueDeliveryDispatchedListener;
use App\Listeners\Subscriptions\LabelRunFailedListener;
use App\Listeners\Subscriptions\LabelRunGeneratedListener;
use App\Listeners\Subscriptions\NotifyAffectedSubscribersListener;
use App\Listeners\Subscriptions\OnInvoicePaymentFailed;
use App\Listeners\Subscriptions\OnInvoicePaymentFailedSendLetter;
use App\Listeners\Subscriptions\OnInvoicePaymentFailedSuspendFulfilments;
use App\Listeners\Subscriptions\OnInvoicePaymentSucceeded;
use App\Listeners\Subscriptions\OnInvoicePaymentSucceededReleaseFulfilments;
use App\Listeners\Subscriptions\OnInvoiceUpcomingSendPaymentCommunication;
use App\Listeners\Subscriptions\OnSubscriptionFirstIssueDeliveredSendCommunication;
use App\Listeners\Subscriptions\OnSubscriptionCancelledByStripe;
use App\Listeners\Subscriptions\OnSubscriptionCancelledCancelFulfilments;
use App\Listeners\Subscriptions\OnSubscriptionPausedPauseFulfilments;
use App\Listeners\Subscriptions\OnSubscriptionResumedResumeFulfilments;
use App\Listeners\Subscriptions\OnSubscriptionSuspendedSuspendFulfilments;
use App\Listeners\Subscriptions\OnSubscriptionUnsuspendedReleaseFulfilments;
use App\Listeners\Subscriptions\RecordSubscriptionHistoryListener;
use App\Models\Block;
use App\Models\Page;
use App\Observers\BlockObserver;
use App\Observers\PageObserver;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\OpenCollab\ImageSubmissionEvidenceRepository;
use App\Repositories\OpenCollab\ImageSubmissionEvidenceRepositoryInterface;
use App\Repositories\OpenCollab\InvitationRepository;
use App\Repositories\OpenCollab\InvitationRepositoryInterface;
use App\Repositories\OpenCollab\UserSiteRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductRepositoryInterface;
use App\Repositories\Vouchers\VoucherRepository;
use App\Services\Billing\PaymentProviders\PaymentIntentGateway;
use App\Services\Billing\PaymentProviders\StripePaymentIntentGateway as LegacyStripePaymentIntentGateway;
use App\Services\Billing\Stripe\BillingAddressResolver;
use App\Services\Billing\Stripe\Contracts\StripeCustomerGatewayInterface;
use App\Services\Billing\Stripe\Contracts\StripePaymentIntentGatewayInterface;
use App\Services\Billing\Stripe\Contracts\StripePriceGatewayInterface;
use App\Services\Billing\Stripe\Contracts\StripeProductGatewayInterface;
use App\Services\Billing\Stripe\Contracts\StripeRefundGatewayInterface;
use App\Services\Billing\Stripe\StripeCouponGateway;
use App\Services\Billing\Stripe\StripeCustomerEmailUpdater;
use App\Services\Billing\Stripe\StripeCustomerDetailsUpdater;
use App\Services\Billing\Stripe\StripeCustomerAddressSynchroniser;
use App\Services\Billing\Stripe\StripeCustomerPaymentMethodService;
use App\Services\Billing\Stripe\StripeCustomerProfileSyncService;
use App\Services\Billing\Stripe\StripeCustomerGateway;
use App\Services\Billing\Stripe\StripeOffSessionCharger;
use App\Services\Billing\Stripe\StripePaymentIntentGateway;
use App\Services\Billing\Stripe\StripePriceGateway;
use App\Services\Billing\Stripe\StripeProductGateway;
use App\Services\Billing\Stripe\StripeRefundGateway;
use App\Services\Billing\Stripe\StripeSubscriptionBillingCycleService;
use App\Services\Billing\Stripe\StripeSubscriptionGateway;
use App\Services\Billing\Stripe\StripeSubscriptionLifecycleService;
use App\Services\Billing\Stripe\StripeSubscriptionPlanUpdater;
use App\Services\Authorization\AuthorisationServiceInterface;
use App\Services\Authorization\ContributorRoleAssignmentInterface;
use App\Services\Authorization\DatabaseAuthorisationService;
use App\Services\Authorization\UserSiteAccessStoreInterface;
use App\Services\Gdpr\Exporters\ActivityExporter;
use App\Services\Gdpr\Exporters\AddressesExporter;
use App\Services\Gdpr\Exporters\CommunicationsExporter;
use App\Services\Gdpr\Exporters\ConsentsExporter;
use App\Services\Gdpr\Exporters\OrdersExporter;
use App\Services\Gdpr\Exporters\PaymentsExporter;
use App\Services\Gdpr\Exporters\ProfileExporter;
use App\Services\Gdpr\Exporters\SubscriptionsExporter;
use App\Services\Gdpr\MemberExportService;
use App\Services\Members\AddressLookupService;
use App\Services\Members\AddressLookupServiceInterface;
use App\Services\Members\Comments\Contracts\SpamDetectionInterface;
use App\Services\Members\Comments\SimpleSpamDetector;
use App\Services\Newsletter\RecommendationResolver;
use App\Services\Newsletter\Renderers\ArticleCardBlockRenderer;
use App\Services\Newsletter\Renderers\ArticleRecommendationsBlockRenderer;
use App\Services\Newsletter\Renderers\AwardBlockRenderer;
use App\Services\Newsletter\Renderers\BannerBlockRenderer;
use App\Services\Newsletter\Renderers\BuyingGuideBlockRenderer;
use App\Services\Newsletter\Renderers\CardBlockRenderer;
use App\Services\Newsletter\Renderers\CardGroupBlockRenderer;
use App\Services\Newsletter\Renderers\ContactFormBlockRenderer;
use App\Services\Newsletter\Renderers\CtaBlockRenderer;
use App\Services\Newsletter\Renderers\DealOfferRenderer;
use App\Services\Newsletter\Renderers\DefaultEmailBlockRendererRegistry;
use App\Services\Newsletter\Renderers\DividerBlockRenderer;
use App\Services\Newsletter\Renderers\EmailBlockRendererRegistry;
use App\Services\Newsletter\Renderers\EventBlockRenderer;
use App\Services\Newsletter\Renderers\GalleryBlockRenderer;
use App\Services\Newsletter\Renderers\HeadingBlockRenderer;
use App\Services\Newsletter\Renderers\HeroBlockRenderer;
use App\Services\Newsletter\Renderers\ImageBlockRenderer;
use App\Services\Newsletter\Renderers\InfoBlockRenderer;
use App\Services\Newsletter\Renderers\ListBlockRenderer;
use App\Services\Newsletter\Renderers\MapLocationBlockRenderer;
use App\Services\Newsletter\Renderers\NewsFeedBlockRenderer;
use App\Services\Newsletter\Renderers\NoteBlockRenderer;
use App\Services\Newsletter\Renderers\OfferBlockRenderer;
use App\Services\Newsletter\Renderers\PageGridBlockRenderer;
use App\Services\Newsletter\Renderers\PageLinksBlockRenderer;
use App\Services\Newsletter\Renderers\PersonBlockRenderer;
use App\Services\Newsletter\Renderers\ProductBlockRenderer;
use App\Services\Newsletter\Renderers\ProductComparisonBlockRenderer;
use App\Services\Newsletter\Renderers\ProductRecommendationBlockRenderer;
use App\Services\Newsletter\Renderers\QuoteBlockRenderer;
use App\Services\Newsletter\Renderers\RecentlyViewedArticlesBlockRenderer;
use App\Services\Newsletter\Renderers\RewardBlockRenderer;
use App\Services\Newsletter\Renderers\SchemaBlockRenderer;
use App\Services\Newsletter\Renderers\SectionBlockRenderer;
use App\Services\Newsletter\Renderers\ServicesBlockRenderer;
use App\Services\Newsletter\Renderers\StaticDealBlockRenderer;
use App\Services\Newsletter\Renderers\StatsBlockRenderer;
use App\Services\Newsletter\Renderers\TableBlockRenderer;
use App\Services\Newsletter\Renderers\TeamBlockRenderer;
use App\Services\Newsletter\Renderers\TeaserBlockRenderer;
use App\Services\Newsletter\Renderers\TestimonialBlockRenderer;
use App\Services\Newsletter\Renderers\TextBlockRenderer;
use App\Services\Newsletter\Renderers\TrendingContentBlockRenderer;
use App\Services\OpenCollab\CmsImageClient;
use App\Services\OpenCollab\CmsImageClientInterface;
use App\Services\OpenCollab\Dashboard\WidgetRegistry;
use App\Services\OpenCollab\Dashboard\Widgets\ActivityWidget;
use App\Services\OpenCollab\Dashboard\Widgets\ApprovalWidget;
use App\Services\OpenCollab\Dashboard\Widgets\DraftsWidget;
use App\Services\OpenCollab\Dashboard\Widgets\EarningsWidget;
use App\Services\OpenCollab\Dashboard\Widgets\OnboardingWidget;
use App\Services\OpenCollab\Dashboard\Widgets\QuickLinksWidget;
use App\Services\OpenCollab\Dashboard\Widgets\ReviewQueueWidget;
use App\Services\OpenCollab\ImageSubmissionEvidenceService;
use App\Services\OpenCollab\ImageSubmissionEvidenceServiceInterface;
use App\Services\OpenCollab\OpenCollabAuthorisation;
use App\Services\OpenCollab\OpenCollabAuthorisationInterface;
use App\Services\OpenCollab\Policies\ContributorImagePolicy;
use App\Services\OpenCollab\Policies\ContributorImagePolicyInterface;
use App\Services\OpenCollab\Policies\ContributorPolicy;
use App\Services\OpenCollab\Policies\ContributorPolicyService;
use App\Services\OpenCollab\SiteRoleAssignmentService;
use App\Services\PublicContent\Config\DatabasePublicContentConfigSource;
use App\Services\PublicContent\Config\PublicContentConfigSource;
use App\Services\PublicContent\Images\Transform\FrameworkLoggerImageTransformLogger;
use App\Services\PublicContent\Images\Transform\ImageTransformer;
use App\Services\PublicContent\Images\Transform\ImageTransformerInterface;
use App\Services\PublicContent\Images\Transform\ImageTransformLogger;
use App\Services\PublicContent\Images\Transform\ImageUrlParameterReader;
use App\Services\PublicContent\Images\Transform\ImageUrlStyleChooser;
use App\Services\PublicContent\Images\Transform\RecognisedImageHostTransformer;
use App\Services\PublicContent\Images\Transform\RichImageUrlBuilder;
use App\Services\PublicContent\Images\Transform\SimpleImageUrlBuilder;
use App\Services\Shared\NativeSessionStore;
use App\Services\Shared\RequestContext;
use App\Services\Shared\SessionStore;
use App\Services\Shared\WebRequestContext;
use App\Services\Shipping\DeliveryEstimatorInterface;
use App\Services\Shipping\HolidayProviderInterface;
use App\Services\Shipping\InternalBusinessDayEstimator;
use App\Services\Shipping\UkHolidayProvider;
use App\Services\Subscriptions\DeliveryChannels\EmailDeliveryChannel;
use App\Services\Subscriptions\DeliveryChannels\PrintDeliveryChannel;
use App\Services\Subscriptions\Printing\Format\CsvPrintExportFormatStrategy;
use App\Services\Subscriptions\Printing\Format\PrintExportFormatStrategy;
use App\Services\Subscriptions\Printing\Label\CsvLabelExportFormatStrategy;
use App\Services\Subscriptions\Printing\Label\LabelFormatStrategyRegistry;
use App\Services\Subscriptions\Printing\Label\PdfLabelExportFormatStrategy;
use App\Repositories\Subscriptions\PrintVendorConnectionRepository;
use App\Services\Subscriptions\Printing\Transport\LabelExportTransport;
use App\Services\Subscriptions\Printing\Transport\LocalLabelExportTransport;
use App\Services\Subscriptions\Printing\Transport\LocalPrintExportTransport;
use App\Services\Subscriptions\Printing\Transport\PrintExportTransport;
use App\Services\Subscriptions\Printing\Transport\SftpLabelExportTransport;
use App\Services\SystemClock;
use App\Services\User\UserLifecycleService;
use App\Services\User\UserLifecycleServiceInterface;
use App\Services\Vouchers\DiscountProviderRegistry;
use App\Services\Vouchers\Providers\OfferDiscountProvider;
use App\Services\Vouchers\Providers\RewardDiscountProvider;
use App\Services\Vouchers\Providers\TieredDiscountProvider;
use App\Services\Vouchers\Providers\VoucherDiscountProvider;
use DateTimeInterface;
use Error;
use Exception;
use RuntimeException;
use Stripe\StripeClient;
use Throwable;
use App\Repositories\OpenCollab\UserTermsAcceptanceRepository;
use App\Repositories\OpenCollab\UserTermsAcceptanceRepositoryInterface;

require_once __DIR__ . '/bootstrap.php';

class ApiApplication
{
    private $container;
    private $router;
    private RouteLoader $routeLoader;

    public function __construct(array $databaseConfig = [], ?Database $database = null)
    {
        // Bootstrap the application with enhanced container.
        // When a Database instance is supplied (functional tests), migrations
        // are skipped — the caller is responsible for having migrated once.
        $this->container = bootstrapApplication($databaseConfig, $database);

        // Create router and register it as singleton in container
        $this->router = new Router($this->container);

        $this->registerMiddleware();

        $this->container->bind(StripePaymentIntentGatewayInterface::class, StripePaymentIntentGateway::class);
        $this->container->bind(StripeCustomerGatewayInterface::class, StripeCustomerGateway::class);
        $this->container->bind(StripeRefundGatewayInterface::class, StripeRefundGateway::class);
        $this->container->bind(UserLifecycleServiceInterface::class, UserLifecycleService::class);
        $this->container->bind(InvitationRepositoryInterface::class, InvitationRepository::class);
        $this->container->bind(UserSiteAccessStoreInterface::class, UserSiteRepository::class);
        $this->container->bind(ContributorRoleAssignmentInterface::class, SiteRoleAssignmentService::class);
        $this->container->bind(AuthorisationServiceInterface::class, DatabaseAuthorisationService::class);
        $this->container->bind(OpenCollabAuthorisationInterface::class, OpenCollabAuthorisation::class);

        $this->container->instance(Router::class, $this->router);
        $this->container->bind(ContributorPolicy::class, ContributorPolicyService::class);
        $this->container->bind(DateTimeInterface::class, Date::class);
        $this->container->bind(RequestContext::class, WebRequestContext::class);
        $this->container->bind(SessionStore::class, NativeSessionStore::class);
        $this->container->bind(DeliveryEstimatorInterface::class, InternalBusinessDayEstimator::class);
        $this->container->bind(HolidayProviderInterface::class, UkHolidayProvider::class);
        $this->container->bind(SpamDetectionInterface::class, SimpleSpamDetector::class);
        $this->container->bind(FileSystemInterface::class, FileSystem::class);
        $this->container->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->container->bind(ClockInterface::class, SystemClock::class);
        $this->container->bind(StripePriceGatewayInterface::class, StripePriceGateway::class);
        //$this->container->bind(StripeProductGatewayInterface::class, StripeProductGateway::class);
        $this->container->bind(StoragePathResolverInterface::class, StoragePathResolver::class);
        $this->container->bind(
            QueueDriverInterface::class,
            ($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'testing'
                ? NullQueueDriver::class
                : DatabaseQueueDriver::class
        );

        //$this->container->bind(StripeProductGatewayInterface::class, StripeProductGateway::class);
        $this->container->bind(AddressLookupServiceInterface::class, AddressLookupService::class);
        //$this->container->bind(PaymentIntentGateway::class, StripePaymentIntentGateway::class);

        $this->container->bind(StripeProductGatewayInterface::class, StripeProductGateway::class);

        $this->container->bind(PrintExportFormatStrategy::class, CsvPrintExportFormatStrategy::class);

        // Bind the appropriate transport based on environment.
        // Local transport is used in development; SFTP in production.
        $this->container->bind(PrintExportTransport::class, function () {
            return new LocalPrintExportTransport(
                config('print.local.export_dir', __DIR__ . '/../storage/exports/print')
            );
        });

        // Label export transport: local/testing envs write to disk so
        // developers don't need a configured vendor connection just to run
        // the app. Every other environment resolves the active default
        // PrintVendorConnection for the label pipeline (host/credentials/
        // path managed via the print vendor connections admin screen —
        // see PrintVendorConnectionController — rather than hardcoded
        // print.label_sftp.* env config).
        $this->container->bind(LabelExportTransport::class, function ($app) {
            $appEnv = (string)(($_ENV['APP_ENV'] ?? getenv('APP_ENV')) ?: 'production');

            if (in_array($appEnv, ['local', 'testing'], true)) {
                return new LocalLabelExportTransport(
                    config('print.local.export_dir', __DIR__ . '/../storage/exports/labels')
                );
            }

            return SftpLabelExportTransport::fromDefault(
                $app->make(PrintVendorConnectionRepository::class)
            );
        });

        $this->container->bind(
            UserTermsAcceptanceRepositoryInterface::class,
            UserTermsAcceptanceRepository::class
        );

        $this->container->singleton(LabelFormatStrategyRegistry::class, function ($app) {
            $registry = new LabelFormatStrategyRegistry();

            $registry->register(
                LabelExportFormat::Pdf,
                $this->container->make(PdfLabelExportFormatStrategy::class)
            );

            $registry->register(
                LabelExportFormat::Csv,
                $this->container->make(CsvLabelExportFormatStrategy::class)
            );

            return $registry;
        });

        $exporters = [
            new ProfileExporter(),
            new AddressesExporter(),
            new OrdersExporter(),
            new PaymentsExporter(),
            new SubscriptionsExporter(),
            new ConsentsExporter(),
            new CommunicationsExporter(),
            new ActivityExporter(),
        ];

        $this->container->bind(MemberExportService::class, fn() => new MemberExportService($exporters));

        $this->container->singleton(WidgetRegistry::class, function () {
            // Fix: Instantiate the registry directly to avoid the infinite loop
            $registry = new WidgetRegistry();

            $widgetPermissions = config('dashboard.widget_permissions', []);

            $registry->register($this->container->make(EarningsWidget::class), $widgetPermissions['earnings'] ?? []);
            $registry->register($this->container->make(DraftsWidget::class), $widgetPermissions['drafts'] ?? []);
            $registry->register($this->container->make(ActivityWidget::class), $widgetPermissions['activity'] ?? []);
            $registry->register($this->container->make(OnboardingWidget::class), $widgetPermissions['onboarding'] ?? []);
            $registry->register($this->container->make(QuickLinksWidget::class), $widgetPermissions['quick_links'] ?? []);
            $registry->register($this->container->make(ReviewQueueWidget::class), $widgetPermissions['review_queue'] ?? []);
            $registry->register($this->container->make(ApprovalWidget::class), $widgetPermissions['approvals'] ?? []);

            foreach (config('dashboard.components', []) as $component) {
                $registry->registerComponent($component);
            }

            return $registry;
        });

        $this->container->bind(ImageTransformLogger::class, FrameworkLoggerImageTransformLogger::class);
        $this->container->singleton(RecognisedImageHostTransformer::class, fn ($app) => new RecognisedImageHostTransformer(
            config('public_content.images.recognised_hosts', []),
            $app->make(\App\Services\PublicContent\Images\Transform\ImageUrlParameterReader::class),
            $app->make(\App\Services\PublicContent\Images\Transform\ImageUrlStyleChooser::class),
            $app->make(\App\Services\PublicContent\Images\Transform\SimpleImageUrlBuilder::class),
            $app->make(\App\Services\PublicContent\Images\Transform\RichImageUrlBuilder::class),
            // Fail-closed base check at library load (separate from SourceImageUrl).
            \App\Services\PublicContent\Images\Transform\ImageBaseUrl::tryFromConfig(
                (string) config('public_content.images.base_url', ''),
            ),
        ));
        $this->container->singleton(ImageTransformer::class, fn ($app) => new ImageTransformer(
            $app->make(RecognisedImageHostTransformer::class),
            $app->make(\App\Services\PublicContent\Images\Transform\PassthroughImageTransformer::class),
            $app->make(ImageTransformLogger::class),
        ));
        $this->container->bind(ImageTransformerInterface::class, ImageTransformer::class);
        $this->container->bind(
            \App\Services\PublicContent\Recirculation\RecirculationSourceLogger::class,
            \App\Services\PublicContent\Recirculation\FrameworkRecirculationSourceLogger::class,
        );
        $this->container->bind(
            \App\Services\PublicContent\Recirculation\RecirculationSourceInterface::class,
            \App\Services\PublicContent\Recirculation\RecirculationRecommendationsSource::class,
        );
        $this->container->singleton(
            \App\Services\PublicContent\Recirculation\BudgetAwareRecirculationResolver::class,
            fn ($app) => new \App\Services\PublicContent\Recirculation\BudgetAwareRecirculationResolver(
                $app->make(\App\Services\PublicContent\Recirculation\RecirculationSourceInterface::class),
                (int) config('public_content.runtime.recirculation_budget_milliseconds', 300),
            ),
        );
        $this->container->bind(
            \App\Services\PublicContent\Navigation\MenuTreeSourceInterface::class,
            \App\Services\PublicContent\Navigation\PublicNavigationMenuTreeSource::class,
        );
        $this->container->singleton(
            \App\Services\PublicContent\Render\PublicContentDefaultLocaleRenderStep::class,
            fn ($app) => new \App\Services\PublicContent\Render\PublicContentDefaultLocaleRenderStep(
                $app->make(\App\Framework\Events\EventDispatcher::class),
                (string) config('public_content.locale.default_language', 'en'),
            ),
        );
        $this->container->singleton(
            \App\Services\PublicContent\Render\PublicContentRenderPipeline::class,
            function ($app) {
                $pipeline = new \App\Services\PublicContent\Render\PublicContentRenderPipeline();
                $pipeline->registerPre(
                    $app->make(\App\Services\PublicContent\Render\PublicContentDefaultLocaleRenderStep::class),
                );
                $pipeline->registerPost(
                    $app->make(\App\Services\PublicContent\Render\PublicContentImageRewriteRenderStep::class),
                );

                return $pipeline;
            },
        );

        $this->container->bind(CmsImageClientInterface::class, CmsImageClient::class);
        $this->container->bind(ContributorImagePolicyInterface::class, ContributorImagePolicy::class);
        $this->container->bind(ImageSubmissionEvidenceServiceInterface::class, ImageSubmissionEvidenceService::class);
        $this->container->bind(ImageSubmissionEvidenceRepositoryInterface::class, ImageSubmissionEvidenceRepository::class);

        $this->container->singleton(StripeClient::class, function () {
            $secretKey = $_ENV['STRIPE_SECRET_KEY']
                ?? config('payment.stripe.secret_key');

            if (!$secretKey) {
                throw new RuntimeException('STRIPE_SECRET_KEY is not configured.');
            }

            return new StripeClient($secretKey);
        });

        $this->container->singleton(
            StripeCustomerGateway::class,
            fn () => new StripeCustomerGateway(
                app(StripeClient::class),
                app(BillingAddressResolver::class),
                app(StripeCustomerAddressSynchroniser::class)
            )
        );

        $this->container->singleton(
            LegacyStripePaymentIntentGateway::class,
            fn () => new LegacyStripePaymentIntentGateway(
                app(StripeClient::class)
            )
        );

        $this->container->singleton(
            StripePaymentIntentGateway::class,
            fn () => new StripePaymentIntentGateway(
                app(StripeClient::class)
            )
        );

        $this->container->singleton(
            StripePriceGateway::class,
            fn () => new StripePriceGateway(
                app(StripeClient::class)
            )
        );

        $this->container->singleton(
            StripeProductGateway::class,
            fn () => new StripeProductGateway(
                app(StripeClient::class)
            )
        );

        $this->container->singleton(
            StripeRefundGateway::class,
            fn () => new StripeRefundGateway(
                app(StripeClient::class)
            )
        );

        $this->container->singleton(
            StripeCustomerAddressSynchroniser::class,
            fn () => new StripeCustomerAddressSynchroniser(
                app(StripeClient::class)
            )
        );

        $this->container->singleton(
            StripeCouponGateway::class,
            fn () => new StripeCouponGateway(
                app(StripeClient::class),
                app(VoucherRepository::class),
                app(Database::class)
            )
        );

        $this->container->singleton(
            StripeSubscriptionGateway::class,
            fn () => new StripeSubscriptionGateway(
                app(StripeClient::class),
                app(StripeCouponGateway::class)
            )
        );

        $this->container->singleton(
            StripeCustomerProfileSyncService::class,
            fn () => new StripeCustomerProfileSyncService(
                app(StripeClient::class),
                app(StripeCustomerGateway::class)
            )
        );

        $this->container->singleton(
            StripeCustomerPaymentMethodService::class,
            fn () => new StripeCustomerPaymentMethodService(
                app(StripeClient::class),
                app(\App\Repositories\Subscriptions\SubscriptionRepository::class)
            )
        );

        $this->container->singleton(
            StripeCustomerEmailUpdater::class,
            fn () => new StripeCustomerEmailUpdater(
                app(StripeClient::class)
            )
        );

        $this->container->singleton(
            StripeCustomerDetailsUpdater::class,
            fn () => new StripeCustomerDetailsUpdater(
                app(StripeClient::class)
            )
        );

        $this->container->singleton(
            StripeSubscriptionLifecycleService::class,
            fn () => new StripeSubscriptionLifecycleService(
                app(StripeClient::class)
            )
        );

        $this->container->singleton(
            StripeSubscriptionBillingCycleService::class,
            fn () => new StripeSubscriptionBillingCycleService(
                app(StripeClient::class)
            )
        );

        $this->container->singleton(
            StripeSubscriptionPlanUpdater::class,
            fn () => new StripeSubscriptionPlanUpdater(
                app(StripeClient::class)
            )
        );

        $this->container->singleton(
            StripeOffSessionCharger::class,
            fn () => new StripeOffSessionCharger(
                app(StripeClient::class)
            )
        );

        // Bind the channel map for DeliverIssueDeliveryJob.
        // Keys are SubscriptionType enum values.
        $this->container->when(DeliverIssueDeliveryJob::class)
            ->needs('$channelMap')
            ->give(function ($app) {
                return [
                    SubscriptionType::DIGITAL->value => $app->make(EmailDeliveryChannel::class),
                    SubscriptionType::PRINTED->value => $app->make(PrintDeliveryChannel::class),
                ];
            });

        // Bind the channel map for DeliverIssueDeliveryJob.
        // Keys are SubscriptionType enum values.
        $this->container->when(NotificationDispatcher::class)
            ->needs('$channels')
            ->give(function ($app) {
                return [
                    $app->make(EmailChannel::class),
                    $app->make(InAppNotificationChannel::class)
                ];
            });


        $this->container->singleton(DiscountProviderRegistry::class, function ($app) {
            $registry = new DiscountProviderRegistry();

            $registry->register($app->make(OfferDiscountProvider::class));
            $registry->register($app->make(TieredDiscountProvider::class));
            $registry->register($app->make(VoucherDiscountProvider::class));
            $registry->register($app->make(RewardDiscountProvider::class));

            return $registry;
        });


        $this->container->bind(
            EmailBlockRendererRegistry::class,
            function () {
                return new DefaultEmailBlockRendererRegistry([
                    new AwardBlockRenderer(),
                    new BannerBlockRenderer(),
                    new BuyingGuideBlockRenderer(),
                    new CardBlockRenderer(),
                    new ContactFormBlockRenderer(),
                    new CtaBlockRenderer(),
                    app(DealOfferRenderer::class),
                    new DividerBlockRenderer(),
                    new GalleryBlockRenderer(),
                    new HeadingBlockRenderer(),
                    new HeroBlockRenderer(),
                    new ImageBlockRenderer(),
                    new InfoBlockRenderer(),
                    new ListBlockRenderer(),
                    new MapLocationBlockRenderer(),
                    new NewsFeedBlockRenderer(),
                    new NoteBlockRenderer(),
                    app(OfferblockRenderer::class),
                    new PersonBlockRenderer(),
                    new ProductBlockRenderer(),
                    new ProductComparisonBlockRenderer(),
                    new QuoteBlockRenderer(),
                    app(RewardBlockRenderer::class),
                    new SchemaBlockRenderer(),
                    new SectionBlockRenderer(),
                    new ServicesBlockRenderer(),
                    new StaticDealBlockRenderer(),
                    new TableBlockRenderer(),
                    new TeamBlockRenderer(),
                    new TeaserBlockRenderer(),
                    new TestimonialBlockRenderer(),
                    new TextBlockRenderer(),
                    new PagelinksBlockRenderer(),
                    new ServicesBlockRenderer(),
                    new CardGroupBlockRenderer(new CardBlockRenderer()),
                    new EventBlockRenderer(),
                    new PagegridBlockRenderer(),
                    new StatsBlockRenderer(),
                    new ArticleCardBlockRenderer(app(PageRepository::class), app(Logger::class)),
                    new ArticleRecommendationsBlockRenderer(app(RecommendationResolver::class)),
                    new ProductRecommendationBlockRenderer(app(RecommendationResolver::class)),
                    new TrendingContentBlockRenderer(app(RecommendationResolver::class)),
                    new RecentlyViewedArticlesBlockRenderer(app(RecommendationResolver::class))
                ]);
            }
        );


        $this->registerEvents();

        // Create route loader using container
        $this->routeLoader = $this->container->resolve(RouteLoader::class);

        // Load routes from separate files
        $this->loadRoutes();

        // Setup other services
        $this->setupArtisan();
        $this->registerObservers();
    }

    private function registerMiddleware()
    {
        $this->router->middleware([
            SiteDetectionMiddleware::class,
            SessionMiddleware::class
        ]);
    }

    /**
     * Load routes from separate files
     */
    private function loadRoutes(): void
    {
        $routeFiles = [
            __DIR__ . '/routes/api.php',

            // Register specific routes before the legacy web routes,
            // particularly before broad dynamic content routes.
            __DIR__ . '/routes/public-content-api.php',
            __DIR__ . '/routes/public-content-preview.php',
            __DIR__ . '/routes/public-directory.php',
            __DIR__ . '/routes/subscription-account.php',

            __DIR__ . '/routes/web.php',
        ];

        $this->routeLoader->loadMultiple(
            array_values(array_filter(
                $routeFiles,
                static fn(string $routeFile): bool => file_exists($routeFile),
            )),
        );
    }

    /**
     * Register model observers
     */
    private function registerObservers(): void
    {
        // These could also be moved to a service provider
        Page::observe($this->container->resolve(PageObserver::class));
        Block::observe($this->container->resolve(BlockObserver::class));

        // Subscribe to global database events
        DatabaseEventSubscriber::subscribe();
    }

    /**
     * Setup Artisan console commands
     */
    public function setupArtisan(): Artisan
    {
        $artisan = $this->container->resolve(Artisan::class);

        // Register commands - these could also be auto-discovered
        $commands = [
            'migrate' => MigrateCommand::class,
            'migrate:rollback' => MigrateRollbackCommand::class,
            'cache:prune' => PruneCacheCommand::class,
            'make:migration' => MakeMigrationCommand::class,
            'make:controller' => MakeControllerCommand::class,
            'make:model' => MakeModelCommand::class,
            'make:repository' => MakeRepositoryCommand::class,
            'db:seed' => SeedCommand::class,
            'schedule:run' => ScheduleRunCommand::class,
            'queue:work' => QueueWorkCommand::class,
            'sync:stripe-plans' => SyncStripePlansCommand::class,
            'sync:stripe-prices' => SyncStripePricesCommand::class
        ];

        foreach ($commands as $name => $commandClass) {
            $artisan->register($name, $commandClass);
        }

        return $artisan;
    }

    /**
     * Handle HTTP request
     */
    public function handleRequest(string $method, string $path, array $data = []): Response
    {
        try {
            $request = $this->container->resolve(Request::class);
            return $this->router->dispatch($method, $path, $request);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Handle exceptions with proper error responses
     */
    private function handleException(Exception|Error $e): Response
    {
        $data = [
            'error' => 'Internal Server Error',
            'message' => $e->getMessage(),
            'status' => 500,
            'timestamp' => date('c')
        ];

        // In development, include stack trace
        if (config('app.debug', false)) {
            $data['trace'] = $e->getTraceAsString();
        }

        return Response::json($data, 500);
    }

    /**
     * Get the container instance
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Get the router instance
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    public function registerEvents()
    {
        $eventDispatcher = new EventDispatcher();
        $this->container->instance(EventDispatcher::class, $eventDispatcher);

        $eventDispatcher->listen(PointsAwardedEvent::class, [PointsAwardedListener::class, 'handle']);
        $eventDispatcher->listen(GiftClaimedEvent::class, [GiftClaimedListener::class, 'handle']);
        $eventDispatcher->listen(GiftCreatedEvent::class, [GiftCreatedListener::class, 'handle']);
        $eventDispatcher->listen(BadgeEarnedEvent::class, [BadgeEarnedListener::class, 'handle']);
        $eventDispatcher->listen(OrderCreatedEvent::class, [SendOrderConfirmationListener::class, 'handle']);
        $eventDispatcher->listen(ProductViewedEvent::class, [TrackProductViewListener::class, 'handle']);
        $eventDispatcher->listen(RefundCreated::class, [LogRefundHistory::class, 'handle']);
        $eventDispatcher->listen(RefundCreated::class, [SendRefundNotification::class, 'handle']);
        $eventDispatcher->listen(OrderCreatedEvent::class, [SendAccountActivationEmailListener::class, 'handle']);

        $eventDispatcher->listen(BoostCreatedEvent::class, [SendBoostCreatedNotification::class, 'handle']);
        $eventDispatcher->listen(BoostActivatedEvent::class, [SendBoostActivatedNotification::class, 'handle']);
        $eventDispatcher->listen(BoostExpiredEvent::class, [SendBoostExpiredNotification::class, 'handle']);
        $eventDispatcher->listen(BoostCancelledEvent::class, [SendBoostCancelledNotification::class, 'handle']);
        $eventDispatcher->listen(BoostPausedEvent::class, [SendBoostPausedNotification::class, 'handle']);
        $eventDispatcher->listen(BoostResumedEvent::class, [SendBoostResumedNotification::class, 'handle']);
        $eventDispatcher->listen(BoostLimitBreachedEvent::class, [SendBoostLimitBreachedNotification::class, 'handle']);
        $eventDispatcher->listen(OrderCreatedEvent::class, [HandleOrderConversionAttribution::class, 'handle']);
        $eventDispatcher->listen(OrderCreatedEvent::class, [ApproveProductLinkedRewardsListener::class, 'handle']);
        $eventDispatcher->listen(MemberRewardApproved::class, [NotifyMemberOnRewardApproval::class, 'handle']);
        $eventDispatcher->listen(OfferExpiryAlertDispatched::class, [LogOfferExpiryAlertDispatched::class, 'handle']);
        $eventDispatcher->listen(
            \App\Events\PublicContent\PublicContentDefaultLocaleApplied::class,
            [\App\Listeners\PublicContent\LogPublicContentDefaultLocaleApplied::class, 'handle'],
        );

        $eventDispatcher->listen(ArticleSubmittedForReviewEvent::class, [SendArticleSentForReviewNotification::class, 'handle']);

        $eventDispatcher->listen(ContentSubmittedForApproval::class, [SendContentWorkflowNotification::class, 'handle']);
        $eventDispatcher->listen(ContentApproved::class, [SendContentWorkflowNotification::class, 'handle']);
        $eventDispatcher->listen(ContentRejected::class, [SendContentWorkflowNotification::class, 'handle']);
        $eventDispatcher->listen(ContentHeld::class, [SendContentWorkflowNotification::class, 'handle']);
        $eventDispatcher->listen(
            ContentEditoriallyModified::class,
            [SendContentWorkflowNotification::class, 'handle']
        );

        $eventDispatcher->listen(MemberAddressImported::class, [MemberPostcodeUpdated::class, 'handle']);
        $eventDispatcher->listen(MemberPostcodeUpdatedListener::class, [MemberPostcodeUpdated::class, 'handle']);
        $eventDispatcher->listen(StockAllocated::class, [StockAllocatedAnalyticsListener::
        class, 'handle']);
        $eventDispatcher->listen(StockReleased::class, [StockConfirmedAnalyticsListener::class, 'handle']);
        $eventDispatcher->listen(StockLow::class, [StockLowAlertListener::class, 'handle']);

        $eventDispatcher->listen(AllProductFulfilmentsCreated::class, [AllProductFulfilmentsCreatedListener::class, 'handle']);
        $eventDispatcher->listen(ProductFulfilmentCreated::class, [ProductFulfilmentCreatedListener::class, 'handle']);
        $eventDispatcher->listen(ProductFulfilmentStalled::class, [NotifyOpsOfStalledProductFulfilmentListener::class, 'handle']);

        $eventDispatcher->listen(SubscriptionPricingChangeScheduled::class, [NotifyAffectedSubscribersListener::class, 'handle']);

        $eventDispatcher->listen(IssueDeliveryDispatched::class, [IssueDeliveryDispatchedListener::class, 'handle']);
        $eventDispatcher->listen(AllFulfilmentsCreated::class, [AllFulfilmentsCreatedListener::class, 'handle']);
        $eventDispatcher->listen(LabelRunFailed::class, [LabelRunFailedListener::class, 'handle']);
        $eventDispatcher->listen(LabelRunGenerated::class, [LabelRunGeneratedListener::class, 'handle']);

        $eventDispatcher->listen(GuidelinesVersionBumpedEvent::class, [InvalidateContributorOnboardingListener::class, 'onGuidelinesBumped']);
        $eventDispatcher->listen(ContractPublishedEvent::class, [InvalidateContributorOnboardingListener::class, 'onContractPublished']);

        $eventDispatcher->listen(PageViewedByMember::class, [RecordMemberEngagementMetric::class, 'handlePageView']);
        $eventDispatcher->listen(PageLikedByMember::class, [RecordMemberEngagementMetric::class, 'handlePageLike']);
        $eventDispatcher->listen(CommentPostedByMember::class, [RecordMemberEngagementMetric::class, 'handleComment']);
        $eventDispatcher->listen(RewardClaimedByMember::class, [RecordMemberEngagementMetric::class, 'handleRewardClaimed']);
        $eventDispatcher->listen(OrderCreatedByMember::class, [RecordMemberEngagementMetric::class, 'handleOrderCreated']);
        $eventDispatcher->listen(PageUnlikedByMember::class, [RecordMemberEngagementMetric::class, 'handlePageUnlike']);

        $eventDispatcher->listen(InvoicePaymentSucceeded::class, [OnInvoicePaymentSucceeded::class, 'handle']);
        $eventDispatcher->listen(InvoicePaymentSucceeded::class, [OnInvoicePaymentSucceededReleaseFulfilments::class, 'handle']);
        $eventDispatcher->listen(InvoicePaymentFailed::class, [OnInvoicePaymentFailed::class, 'handle']);
        $eventDispatcher->listen(InvoicePaymentFailed::class, [OnInvoicePaymentFailedSendLetter::class, 'handle']);
        $eventDispatcher->listen(InvoicePaymentFailed::class, [OnInvoicePaymentFailedSuspendFulfilments::class, 'handle']);
        $eventDispatcher->listen(InvoiceUpcoming::class, [OnInvoiceUpcomingSendPaymentCommunication::class, 'handle']);
        $eventDispatcher->listen(SubscriptionFirstIssueDelivered::class, [OnSubscriptionFirstIssueDeliveredSendCommunication::class, 'handle']);
        $eventDispatcher->listen(SubscriptionCancelledByStripe::class, [OnSubscriptionCancelledByStripe::class, 'handle']);
        $eventDispatcher->listen(SubscriptionCancelledByStripe::class, [OnSubscriptionCancelledCancelFulfilments::class, 'handleCancelledByStripe']);
        $eventDispatcher->listen(SubscriptionCancelled::class, [OnSubscriptionCancelledCancelFulfilments::class, 'handle']);
        $eventDispatcher->listen(SubscriptionSuspended::class, [OnSubscriptionSuspendedSuspendFulfilments::class, 'handle']);
        $eventDispatcher->listen(SubscriptionUnsuspended::class, [OnSubscriptionUnsuspendedReleaseFulfilments::class, 'handle']);
        $eventDispatcher->listen(SubscriptionPaused::class, [OnSubscriptionPausedPauseFulfilments::class, 'handle']);
        $eventDispatcher->listen(SubscriptionResumed::class, [OnSubscriptionResumedResumeFulfilments::class, 'handle']);

        $eventDispatcher->listen(ArticleApprovedEvent::class, [SendArticleApprovedNotification::class, 'handle']);
        $eventDispatcher->listen(ArticleRejectedEvent::class, [SendArticleRejectedNotification::class, 'handle']);
        $eventDispatcher->listen(ArticleNeedsChangesEvent::class, [SendArticleNeedsChangesNotification::class, 'handle']);
        $eventDispatcher->listen(PayoutProcessedEvent::class, [SendPayoutProcessedNotification::class, 'handle']);
        $eventDispatcher->listen(PayoutFailedEvent::class, [SendPayoutFailedNotification::class, 'handle']);
        $eventDispatcher->listen(DisputeRaisedEvent::class, [SendDisputeRaisedNotification::class, 'handle']);
        $eventDispatcher->listen(DisputeResolvedEvent::class, [SendDisputeResolvedNotification::class, 'handle']);
        $eventDispatcher->listen(ContractPublishedEvent::class, [SendContractPublishedNotification::class, 'handle']);
        $eventDispatcher->listen(GuidelinesVersionBumpedEvent::class, [SendGuidelinesUpdatedNotification::class, 'handle']);
        $eventDispatcher->listen(ViolationRecordedEvent::class, [SendViolationRecordedNotification::class, 'handle']);

        $eventDispatcher->listen(SubscriptionCreated::class, [RecordSubscriptionHistoryListener::class, 'handleSubscriptionCreated']);
        $eventDispatcher->listen(SubscriptionCancelled::class, [RecordSubscriptionHistoryListener::class, 'handleSubscriptionCancelled']);
        $eventDispatcher->listen(SubscriptionReactivated::class, [RecordSubscriptionHistoryListener::class, 'handleSubscriptionReactivated']);
        $eventDispatcher->listen(SubscriptionPaused::class, [RecordSubscriptionHistoryListener::class, 'handleSubscriptionPaused']);
        $eventDispatcher->listen(SubscriptionResumed::class, [RecordSubscriptionHistoryListener::class, 'handleSubscriptionResumed']);
        $eventDispatcher->listen(PaymentSucceeded::class, [RecordSubscriptionHistoryListener::class, 'handlePaymentSucceeded']);
        $eventDispatcher->listen(PaymentFailed::class, [RecordSubscriptionHistoryListener::class, 'handlePaymentFailed']);
        $eventDispatcher->listen(PaymentRefunded::class, [RecordSubscriptionHistoryListener::class, 'handlePaymentRefunded']);
        $eventDispatcher->listen(SubscriptionRenewedAndReplaced::class, [RecordSubscriptionHistoryListener::class, 'handleSubscriptionRenewedAndReplaced']);
        $eventDispatcher->listen(SubscriptionProductChanged::class, [RecordSubscriptionHistoryListener::class, 'handleSubscriptionProductChanged']);
        $eventDispatcher->listen(MemberDetailsChanged::class, [SyncMemberToStripeListener::class, 'handle']);
        $eventDispatcher->listen(ArticlePurchasedEvent::class, [RecordSaleToEarningsLedger::class, 'handle']);

        $eventDispatcher->listen(ChangesRequestedEvent::class, [NotifyContributorOfRequestedChangesListener::class, 'handle']);


    }
}
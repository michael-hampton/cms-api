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
use App\Events\DatabaseEventSubscriber;
use App\Events\Members\CommentPostedByMember;
use App\Events\Members\MemberAddressImported;
use App\Events\Members\MemberPostcodeUpdated;
use App\Events\Members\OrderCreatedByMember;
use App\Events\Members\PageLikedByMember;
use App\Events\Members\PageViewedByMember;
use App\Events\Members\RewardClaimedByMember;
use App\Events\OpenCollab\ContractPublishedEvent;
use App\Events\OpenCollab\GuidelinesVersionBumpedEvent;
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
use App\Events\Subscriptions\IssueDeliveryDispatched;
use App\Events\Subscriptions\LabelRunFailed;
use App\Events\Subscriptions\LabelRunGenerated;
use App\Framework\Console\Artisan;
use App\Framework\Console\Commands\MakeControllerCommand;
use App\Framework\Console\Commands\MakeMigrationCommand;
use App\Framework\Console\Commands\MakeModelCommand;
use App\Framework\Console\Commands\MakeRepositoryCommand;
use App\Framework\Console\Commands\MigrateCommand;
use App\Framework\Console\Commands\MigrateRollbackCommand;
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
use App\Framework\Notifications\NotificationDispatcher;
use App\Framework\Queue\DatabaseQueueDriver;
use App\Framework\Queue\NullQueueDriver;
use App\Framework\Queue\QueueDriverInterface;
use App\Framework\Routing\RouteLoader;
use App\Framework\Storage\StoragePathResolver;
use App\Framework\Storage\StoragePathResolverInterface;
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
use App\Listeners\GiftClaimedListener;
use App\Listeners\GiftCreatedListener;
use App\Listeners\Members\MemberPostcodeUpdatedListener;
use App\Listeners\Members\RecordMemberEngagementMetric;
use App\Listeners\Members\SendAccountActivationEmailListener;
use App\Listeners\OpenCollab\InvalidateContributorOnboardingListener;
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
use App\Models\Block;
use App\Models\Page;
use App\Observers\BlockObserver;
use App\Observers\PageObserver;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductRepositoryInterface;
use App\Services\Billing\PaymentProviders\PaymentIntentGateway;
use App\Services\Billing\PaymentProviders\StripePaymentIntentGateway;
use App\Services\Billing\Stripe\Contracts\StripePriceGatewayInterface;
use App\Services\Billing\Stripe\Contracts\StripeProductGatewayInterface;
use App\Services\Billing\Stripe\StripePriceGateway;
use App\Services\Billing\Stripe\StripeProductGateway;
use App\Services\Members\AddressLookupService;
use App\Services\Members\AddressLookupServiceInterface;
use App\Services\Members\Comments\Contracts\SpamDetectionInterface;
use App\Services\Members\Comments\SimpleSpamDetector;
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
use App\Services\Newsletter\Renderers\QuoteBlockRenderer;
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
use App\Services\Subscriptions\Printing\Transport\LocalPrintExportTransport;
use App\Services\Subscriptions\Printing\Transport\PrintExportTransport;
use App\Services\SystemClock;
use App\Services\Vouchers\DiscountProviderRegistry;
use App\Services\Vouchers\Providers\OfferDiscountProvider;
use App\Services\Vouchers\Providers\RewardDiscountProvider;
use App\Services\Vouchers\Providers\TieredDiscountProvider;
use App\Services\Vouchers\Providers\VoucherDiscountProvider;
use Exception;

require_once __DIR__ . '/bootstrap.php';

class ApiApplication
{
    private $container;
    private $router;
    private RouteLoader $routeLoader;

    public function __construct(array $databaseConfig = [], ?Database $database = null)
    {
        // Bootstrap the application with enhanced container
        $this->container = bootstrapApplication($databaseConfig, $database);

        // Create router and register it as singleton in container
        $this->router = new Router($this->container);

        $this->registerMiddleware();

        $this->container->instance(Router::class, $this->router);
        $this->container->bind(\DateTimeInterface::class, Date::class);
        $this->container->bind(RequestContext::class, WebRequestContext::class);
        $this->container->bind(SessionStore::class, NativeSessionStore::class);
        $this->container->bind(DeliveryEstimatorInterface::class, InternalBusinessDayEstimator::class);
        $this->container->bind(HolidayProviderInterface::class, UkHolidayProvider::class);
        $this->container->bind(SpamDetectionInterface::class, SimpleSpamDetector::class);
        $this->container->bind(FileSystemInterface::class, FileSystem::class);
        $this->container->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->container->bind(ClockInterface::class, SystemClock::class);
        $this->container->bind(StripePriceGatewayInterface::class, StripePriceGateway::class);
        $this->container->bind(StripeProductGatewayInterface::class, StripeProductGateway::class);
        $this->container->bind(StoragePathResolverInterface::class, StoragePathResolver::class);
        $this->container->bind(
            QueueDriverInterface::class,
            ($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'testing'
                ? NullQueueDriver::class
                : DatabaseQueueDriver::class
        );
        $this->container->bind(StripeProductGatewayInterface::class, StripeProductGateway::class);
        $this->container->bind(AddressLookupServiceInterface::class, AddressLookupService::class);
        $this->container->bind(PaymentIntentGateway::class, StripePaymentIntentGateway::class);

        $this->container->bind(PrintExportFormatStrategy::class, CsvPrintExportFormatStrategy::class);

        // Bind the appropriate transport based on environment.
        // Local transport is used in development; SFTP in production.
        $this->container->bind(PrintExportTransport::class, function () {
            return new LocalPrintExportTransport(
                config('print.local.export_dir', __DIR__ . '/../storage/exports/print')
            );
        });

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
                    $app->make(EmailChannel::class)
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
                    new StatsBlockRenderer()
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
            'routes/api.php',
            'routes/web.php',
        ];

        foreach ($routeFiles as $routeFile) {
            if (file_exists($routeFile)) {
                $this->routeLoader->load($routeFile);
            }
        }
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
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Handle exceptions with proper error responses
     */
    private function handleException(Exception $e): Response
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

        return Response::json($data, 302);
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

        $eventDispatcher->listen(MemberAddressImported::class, [MemberPostcodeUpdated::class, 'handle']);
        $eventDispatcher->listen(MemberPostcodeUpdatedListener::class, [MemberPostcodeUpdated::class, 'handle']);
        $eventDispatcher->listen(StockAllocated::class, [StockAllocatedAnalyticsListener::
        class, 'handle']);
        $eventDispatcher->listen(StockReleased::class, [StockConfirmedAnalyticsListener::class, 'handle']);
        $eventDispatcher->listen(StockLow::class, [StockLowAlertListener::class, 'handle']);

        $eventDispatcher->listen(AllProductFulfilmentsCreated::class, [AllProductFulfilmentsCreatedListener::class, 'handle']);
        $eventDispatcher->listen(ProductFulfilmentCreated::class, [ProductFulfilmentCreatedListener::class, 'handle']);
        $eventDispatcher->listen(ProductFulfilmentStalled::class, [NotifyOpsOfStalledProductFulfilmentListener::class, 'handle']);


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
    }
}
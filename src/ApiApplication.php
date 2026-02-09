<?php

namespace App;

use App\Events\ArticleGifting\GiftClaimedEvent;
use App\Events\ArticleGifting\GiftCreatedEvent;
use App\Events\Badges\BadgeEarnedEvent;
use App\Events\Badges\PointsAwardedEvent;
use App\Events\DatabaseEventSubscriber;
use App\Events\Orders\OrderCreatedEvent;
use App\Events\Products\ProductViewedEvent;
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
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Http\Router;
use App\Framework\Middleware\SessionMiddleware;
use App\Framework\Middleware\SiteDetectionMiddleware;
use App\Framework\Routing\RouteLoader;
use App\Listeners\BadgeEarnedListener;
use App\Listeners\GiftClaimedListener;
use App\Listeners\GiftCreatedListener;
use App\Listeners\Orders\SendOrderConfirmationListener;
use App\Listeners\PointsAwardedListener;
use App\Listeners\Products\TrackProductViewListener;
use App\Models\Block;
use App\Models\Page;
use App\Observers\BlockObserver;
use App\Observers\PageObserver;
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
use App\Services\Newsletter\Renderers\TableBlockRenderer;
use App\Services\Newsletter\Renderers\TeamBlockRenderer;
use App\Services\Newsletter\Renderers\TeaserBlockRenderer;
use App\Services\Newsletter\Renderers\TestimonialBlockRenderer;
use App\Services\Newsletter\Renderers\TextBlockRenderer;
use App\Services\Shared\NativeSessionStore;
use App\Services\Shared\RequestContext;
use App\Services\Shared\SessionStore;
use App\Services\Shared\WebRequestContext;
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

        $this->container->bind(
            EmailBlockRendererRegistry::class,
            function () {
                return new DefaultEmailBlockRendererRegistry([
                    new AwardBlockRenderer(),
                    new BannerBlockRenderer(),
                    new BuyingGuideBlockRenderer(),
                    new ContactFormBlockRenderer(),
                    new CtaBlockRenderer(),
                    app(DealOfferRenderer::class),
                    new DividerBlockRenderer(),
                    new HeadingBlockRenderer(),
                    new HeroBlockRenderer(),
                    new ImageBlockRenderer(),
                    new InfoBlockRenderer(),
                    new ListBlockRenderer(),
                    new NoteBlockRenderer(),
                    app(OfferblockRenderer::class),
                    new PersonBlockRenderer(),
                    new ProductBlockRenderer(),
                    new ProductComparisonBlockRenderer(),
                    new QuoteBlockRenderer(),
                    app(RewardBlockRenderer::class),
                    new SchemaBlockRenderer(),
                    new SectionBlockRenderer(),
                    new StaticDealBlockRenderer(),
                    new TableBlockRenderer(),
                    new TestimonialBlockRenderer(),
                    new TextBlockRenderer(),
                    new PagelinksBlockRenderer(),
                    new ServicesBlockRenderer(),
                    new NewsFeedBlockRenderer(),
                    new CardBlockRenderer(),
                    new CardGroupBlockRenderer(new CardBlockRenderer()),
                    new TeaserBlockRenderer(),
                    new TeamBlockRenderer(),
                    new GalleryBlockRenderer(),
                    new EventBlockRenderer(),
                    new MapLocationBlockRenderer(),
                    new PagegridBlockRenderer(),
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

    }
}
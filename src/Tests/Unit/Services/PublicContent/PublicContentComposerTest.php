<?php

namespace App\Tests\Unit\Services\PublicContent;

use App\DTO\PublicContent\PublicContentContext;
use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Framework\View\ViewRenderer;
use App\Models\Page;
use App\Repositories\PublicContent\Contracts\PageWidgetRepositoryInterface;
use App\Services\Cms\Pages\PremiumPagePurchaseEligibilityService;
use App\Services\PublicContent\Composition\PublicContentComposer;
use App\Services\PublicContent\Composition\RegionalPublicContentComponentFactory;
use App\Services\PublicContent\Diagnostics\PublicContentDiagnosticsReportWriter;
use App\Services\PublicContent\Diagnostics\PublicContentWidgetDiagnostics;
use App\Services\PublicContent\Hero\PublicContentHeroDataResolver;
use App\Services\PublicContent\PageReviewDataFactory;
use App\Services\PublicContent\Paywall\PublicContentPaywallModeResolver;
use App\Services\PublicContent\Widgets\BuiltInPublicContentWidgetCatalog;
use App\Services\PublicContent\Widgets\PageWidgetLayoutResolver;
use App\Services\PublicContent\Widgets\PaywallOverlayWidget;
use App\Services\PublicContent\Widgets\PublicContentWidgetEligibility;
use App\Services\PublicContent\Widgets\PublicContentWidgetRegistry;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicContentComposerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testArticleWidgets(): void
    {
        $regions = $this->compose('article', true);

        // Updated to include 'hero-block' at the start of the header region
        self::assertSame(
            ['hero-block', 'page-title', 'category-pills', 'tags', 'page-actions'],
            $this->types($regions['header']),
        );
        self::assertSame(['authors'], $this->types($regions['below-content']));
    }

    public function testLandingWidgets(): void
    {
        $regions = $this->compose('landing-page');

        // Changed from assertArrayNotHasKey to assert that the header now contains the 'hero-block'
        self::assertContains('hero-block', $this->types($regions['header'] ?? []));
        self::assertContains('newsletter-signup-widget', $this->types($regions['after-content']));
        self::assertContains('guest-contributors', $this->types($regions['below-content']));
    }

    public function testStaticContentOmitsEditorialWidgetsAndActions(): void
    {
        $regions = $this->compose('content', true);

        // Reverted back to ensure the header region is completely omitted
        // for static content pages
        self::assertArrayNotHasKey('header', $regions);
        self::assertSame(['authors'], $this->types($regions['below-content']));
        self::assertNotContains('comments', $this->types($regions['after-content']));
    }

    private function compose(string $pageType, bool $withAuthor = false): array
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 42;
        $page->page_type = $pageType;
        $page->products = new Collection();
        $page->authors = $withAuthor
            ? new Collection([(object) ['name' => 'Author', 'slug' => 'author']])
            : new Collection();
        $page->title = 'Example';
        $page->slug = 'example';
        $page->is_paid = false;
        $page->is_public_contribution = false;

        $views = Mockery::mock(ViewRenderer::class);
        $views->shouldReceive('partial')->andReturn('<div>component</div>');

        $repository = Mockery::mock(PageWidgetRepositoryInterface::class);
        $repository->shouldReceive('getForPage')->with(42)->andReturn(new Collection());

        // Fix 1: Mock the eligibility service for the Paywall Mode Resolver
        $eligibility = Mockery::mock(PremiumPagePurchaseEligibilityService::class);
        $eligibility->shouldReceive('isPurchasable')->andReturn(false)->byDefault();

        $paywallMode = new PublicContentPaywallModeResolver($eligibility);
        $registry = new PublicContentWidgetRegistry([
            new PaywallOverlayWidget($views, $paywallMode),
        ]);

        // Fix 2: Mock the new dependencies required by BuiltInPublicContentWidgetCatalog
        // (Adjust the namespace strings below if they live in a different directory)
        $heroData = Mockery::mock(PublicContentHeroDataResolver::class)->makePartial();
        $reviewData = Mockery::mock(PageReviewDataFactory::class)->makePartial();

        $reportWriter = Mockery::mock(PublicContentDiagnosticsReportWriter::class)->shouldIgnoreMissing();
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();
        $diagnostics = new PublicContentWidgetDiagnostics($reportWriter, $logger);

        $composer = new PublicContentComposer(
            new BuiltInPublicContentWidgetCatalog(
                $views,
                new PublicContentWidgetEligibility(),
                $heroData,
                $reviewData
            ),
            new RegionalPublicContentComponentFactory($views),
            $registry,
            new PageWidgetLayoutResolver($repository),
            $diagnostics, // Pass the newly constructed diagnostics instance here
        );

        return $composer->compose(new PublicContentContext(
            page: $page,
            siteId: 1,
            siteSlug: 'estate',
            member: null,
            viewData: [
                'access' => ['can_view' => true, 'reason' => null],
                'categories' => new Collection(),
                'categoriesWithPages' => [],
                'feedPages' => new Collection(),
                'trendingPages' => new Collection(),
                'todaysDeals' => [],
                'links' => [
                    'viewer_state' => '/viewer',
                    'comments' => '/comments',
                    'like' => '/like',
                    'view' => '/views',
                ],
            ],
        ));
    }

    private function types(array $components): array
    {
        return array_map(static fn($component) => $component->type, $components);
    }
}

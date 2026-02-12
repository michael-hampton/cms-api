<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Framework\Support\Logger;
use App\Models\Author;
use App\Models\Block;
use App\Models\Category;
use App\Models\Newsletter;
use App\Models\Page;
use App\Models\PageAuthor;
use App\Models\PageCategory;
use App\Models\PageMetadata;
use App\Models\PageTag;
use App\Models\ProductOffer;
use App\Models\Site;
use App\Models\Tag;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Rewards\RewardsRepository;
use App\Services\Adverts\DealVisibilityResolver;
use App\Services\Adverts\OfferVisibilityResolver;
use App\Services\Adverts\PromotionInjector;
use App\Services\Adverts\RewardVisibilityResolver;
use App\Services\Newsletter\NewsletterPageBuilderService;
use App\Services\Newsletter\Renderers\AwardBlockRenderer;
use App\Services\Newsletter\Renderers\BannerBlockRenderer;
use App\Services\Newsletter\Renderers\BuyingGuideBlockRenderer;
use App\Services\Newsletter\Renderers\ContactFormBlockRenderer;
use App\Services\Newsletter\Renderers\CtaBlockRenderer;
use App\Services\Newsletter\Renderers\DealOfferRenderer;
use App\Services\Newsletter\Renderers\DefaultEmailBlockRendererRegistry;
use App\Services\Newsletter\Renderers\DividerBlockRenderer;
use App\Services\Newsletter\Renderers\HeadingBlockRenderer;
use App\Services\Newsletter\Renderers\HeroBlockRenderer;
use App\Services\Newsletter\Renderers\ImageBlockRenderer;
use App\Services\Newsletter\Renderers\InfoBlockRenderer;
use App\Services\Newsletter\Renderers\ListBlockRenderer;
use App\Services\Newsletter\Renderers\NoteBlockRenderer;
use App\Services\Newsletter\Renderers\OfferBlockRenderer;
use App\Services\Newsletter\Renderers\PersonBlockRenderer;
use App\Services\Newsletter\Renderers\ProductBlockRenderer;
use App\Services\Newsletter\Renderers\ProductComparisonBlockRenderer;
use App\Services\Newsletter\Renderers\QuoteBlockRenderer;
use App\Services\Newsletter\Renderers\RewardBlockRenderer;
use App\Services\Newsletter\Renderers\SchemaBlockRenderer;
use App\Services\Newsletter\Renderers\SectionBlockRenderer;
use App\Services\Newsletter\Renderers\StaticDealBlockRenderer;
use App\Services\Newsletter\Renderers\StatsBlockRenderer;
use App\Services\Newsletter\Renderers\TableBlockRenderer;
use App\Services\Newsletter\Renderers\TestimonialBlockRenderer;
use App\Services\Newsletter\Renderers\TextBlockRenderer;
use App\Services\Newsletter\Services\BlockDataFactory;
use App\Services\Newsletter\Services\DealTrackingService;
use App\Services\Newsletter\Services\OfferTrackingService;
use App\Services\Newsletter\Services\RewardTrackingService;
use App\Services\Newsletter\Services\TrackingUrlBuilder;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class NewsletterPageBuilderServiceTest extends RepositoryTestCase
{
    use CreatesTestData;

    private NewsletterPageBuilderService $service;
    private Logger $logger;
    private TrackingUrlBuilder $trackingUrlBuilder;
    private $newsletterRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = app(Logger::class);
        $this->trackingUrlBuilder = new TrackingUrlBuilder();
        $blockDataFactory = new BlockDataFactory();

        // Bind interface to implementation

        // Create eligibility services
        $dealEligibilityService = app(DealVisibilityResolver::class);
        $offerEligibilityService = app(OfferVisibilityResolver::class);
        $rewardEligibilityService = app(RewardVisibilityResolver::class);
        $this->newsletterRepository = app(NewsletterRepository::class);

        // Create tracking services
        $dealTrackingService = app(DealTrackingService::class);
        $offerTrackingService = app(OfferTrackingService::class);
        $rewardTrackingService = app(RewardTrackingService::class);

        // Create ALL renderers
        $renderers = [
            new DealOfferRenderer(
                app(ProductRepository::class),
                $dealEligibilityService,
                $dealTrackingService,
                $this->trackingUrlBuilder,
                $this->logger
            ),
            new OfferBlockRenderer(
                app(ProductOfferRepository::class),
                $offerEligibilityService,
                $offerTrackingService,
                $this->trackingUrlBuilder,
                $this->logger
            ),
            new RewardBlockRenderer(
                app(RewardsRepository::class),
                $rewardEligibilityService,
                $rewardTrackingService,
                $this->trackingUrlBuilder,
                $this->logger
            ),
            new TextBlockRenderer(),
            new HeadingBlockRenderer(),
            new ImageBlockRenderer(),
            new ListBlockRenderer(),
            new QuoteBlockRenderer(),
            new CtaBlockRenderer(),
            new ProductBlockRenderer(),
            new TableBlockRenderer(),
            new StatsBlockRenderer(),
            new TestimonialBlockRenderer(),
            new DividerBlockRenderer(),
            new BannerBlockRenderer(),
            new HeroBlockRenderer(),
            new InfoBlockRenderer(),
            new SectionBlockRenderer(),
            new PersonBlockRenderer(),
            new ProductComparisonBlockRenderer(),
            new SchemaBlockRenderer(),
            new AwardBlockRenderer(),
            new NoteBlockRenderer(),
            new BuyingGuideBlockRenderer(),
            new ContactFormBlockRenderer(),
            new StaticDealBlockRenderer(),
        ];

        $this->service = new NewsletterPageBuilderService(
            app(PageRepository::class),
            app(PromotionInjector::class),
            $this->trackingUrlBuilder,
            $blockDataFactory,
            $this->logger,
            $this->newsletterRepository,
            new DefaultEmailBlockRendererRegistry($renderers)
        );
    }

    public function test_gets_pages_for_automated_newsletter(): void
    {
        // Arrange
        $page1 = Page::create([
            'title' => 'Test Page 1',
            'slug' => 'test-page-1',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'site_id' => $this->siteId
        ]);

        $page2 = Page::create([
            'title' => 'Test Page 2',
            'slug' => 'test-page-2',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Weekly Digest',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'page_filters' => ['date_range_days' => 7],
            'max_pages' => 10,
            'content' => 'test-page-1,test-page-2'
        ]);

        // Act
        $pages = $this->service->getPagesForNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertCount(2, $pages);
        $this->assertEquals($page1->id, $pages->first()->id);
    }

    public function test_filters_pages_by_category(): void
    {
        // Arrange
        $category = Category::create([
            'name' => 'News',
            'slug' => 'news',
            'site_id' => $this->siteId
        ]);

        $page1 = Page::create([
            'title' => 'News Page',
            'slug' => 'news-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        PageCategory::create([
            'page_id' => $page1->id,
            'category_id' => $category->id,
            'site_id' => $this->siteId
        ]);

        $page2 = Page::create([
            'title' => 'Other Page',
            'slug' => 'other-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'News Digest',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'page_filters' => [
                'categories' => [$category->id],
                'date_range_days' => 7
            ],
            'content' => 'news-page'
        ]);
        // Act
        $pages = $this->service->getPagesForNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertCount(1, $pages);
        $this->assertEquals($page1->id, $pages->first()->id);
    }

    public function test_filters_pages_by_tag(): void
    {
        // Arrange
        $tag = Tag::create([
            'name' => 'Technology',
            'slug' => 'technology',
            'site_id' => $this->siteId
        ]);

        $page1 = Page::create([
            'title' => 'Tech Page',
            'slug' => 'tech-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        PageTag::create([
            'page_id' => $page1->id,
            'tag_id' => $tag->id,
            'site_id' => $this->siteId
        ]);

        $page2 = Page::create([
            'title' => 'Other Page',
            'slug' => 'other-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Tech Digest',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'page_filters' => [
                'tags' => [$tag->id],
                'date_range_days' => 7
            ],
            'content' => 'tech-page'
        ]);

        // Act
        $pages = $this->service->getPagesForNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertCount(1, $pages);
        $this->assertEquals($page1->id, $pages->first()->id);
    }

    public function test_filters_pages_by_page_type(): void
    {
        // Arrange
        $page1 = Page::create([
            'title' => 'Article Page',
            'slug' => 'article-page',
            'status' => 'published',
            'page_type' => 'article',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $page2 = Page::create([
            'title' => 'Review Page',
            'slug' => 'review-page',
            'status' => 'published',
            'page_type' => 'review',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Articles Digest',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'page_filters' => [
                'page_types' => ['article'],
                'date_range_days' => 7
            ],
            'content' => 'article-page'
        ]);

        // Act
        $pages = $this->service->getPagesForNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertCount(1, $pages);
        $this->assertEquals($page1->id, $pages->first()->id);
    }

    public function test_filters_pages_since_last_send(): void
    {
        // Arrange
        $oldPage = Page::create([
            'title' => 'Old Page',
            'slug' => 'old-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
            'site_id' => $this->siteId
        ]);

        $newPage = Page::create([
            'title' => 'New Page',
            'slug' => 'new-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Weekly Digest',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'content' => 'old-page,new-page'
        ]);

        // Act
        $pages = $this->service->getPagesForNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertCount(1, $pages);
        $this->assertEquals($newPage->id, $pages->first()->id);
    }

    public function test_limits_pages_by_max_pages(): void
    {
        // Arrange
        for ($i = 1; $i <= 5; $i++) {
            Page::create([
                'title' => "Page {$i}",
                'slug' => "page-{$i}",
                'status' => 'published',
                'published_at' => date('Y-m-d H:i:s', strtotime("-{$i} days")),
                'site_id' => $this->siteId
            ]);
        }

        $newsletter = Newsletter::create([
            'title' => 'Limited Digest',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'page_filters' => ['date_range_days' => 10],
            'max_pages' => 3,
            'content' => 'page-1,page-2,page-3'
        ]);

        // Act
        $pages = $this->service->getPagesForNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertCount(3, $pages);
    }

    public function test_filters_featured_pages_only(): void
    {
        // Arrange
        $featuredPage = Page::create([
            'title' => 'Featured Page',
            'slug' => 'featured-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        PageMetadata::create([
            'page_id' => $featuredPage->id,
            'featured' => true,
            'site_id' => $this->siteId
        ]);

        $regularPage = Page::create([
            'title' => 'Regular Page',
            'slug' => 'regular-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Featured Digest',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'page_filters' => [
                'featured_only' => true,
                'date_range_days' => 7
            ],
            'content' => 'featured-page'
        ]);

        // Act
        $pages = $this->service->getPagesForNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertCount(1, $pages);
        $this->assertEquals($featuredPage->id, $pages->first()->id);
    }

    public function test_sorts_pages_by_custom_field(): void
    {
        // Arrange
        $page1 = Page::create([
            'title' => 'Zulu Page',
            'slug' => 'zulu-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'site_id' => $this->siteId
        ]);

        $page2 = Page::create([
            'title' => 'Alpha Page',
            'slug' => 'alpha-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Sorted Digest',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'page_filters' => ['date_range_days' => 7],
            'sort_by' => 'title',
            'sort_order' => 'asc',
            'content' => 'sorted-pages'
        ]);

        // Act
        $pages = $this->service->getPagesForNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertCount(2, $pages);
        $this->assertEquals('Alpha Page', $pages->first()->title);
        $this->assertEquals('Zulu Page', $pages->last()->title);
    }

    public function test_excludes_draft_pages(): void
    {
        // Arrange
        $publishedPage = Page::create([
            'title' => 'Published Page',
            'slug' => 'published-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $draftPage = Page::create([
            'title' => 'Draft Page',
            'slug' => 'draft-page',
            'status' => 'draft',
            'published_at' => null,
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Published Only',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'page_filters' => ['date_range_days' => 7],
            'content' => 'published-page'
        ]);

        // Act
        $pages = $this->service->getPagesForNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertCount(1, $pages);
        $this->assertEquals($publishedPage->id, $pages->first()->id);
    }

    public function test_builds_default_template_html(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Test Article',
            'slug' => 'test-article',
            'status' => 'published',
            'meta_description' => 'This is a test article description',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Weekly Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test-token'
        ]);

        $pages = collect([$page]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, $pages, null, 'test-token', false, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('Test Article', $html);
        $this->assertStringContainsString('This is a test article description', $html);
        $this->assertStringContainsString('test-token', $html);
    }

    public function test_builds_digest_template_html(): void
    {
        // Arrange
        $page1 = Page::create([
            'title' => 'Article 1',
            'slug' => 'article-1',
            'status' => 'published',
            'meta_description' => 'Description 1',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $page2 = Page::create([
            'title' => 'Article 2',
            'slug' => 'article-2',
            'status' => 'published',
            'meta_description' => 'Description 2',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Daily Digest',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'digest',
            'interval' => Newsletter::INTERVAL_DAILY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => '2 latest articles'
        ]);

        $pages = collect([$page1, $page2]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, $pages, null, null, true, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('2 articles', $html);
        $this->assertStringContainsString('Article 1', $html);
        $this->assertStringContainsString('Article 2', $html);
    }

    public function test_builds_featured_template_html(): void
    {
        // Arrange
        $featuredPage = Page::create([
            'title' => 'Featured Article',
            'slug' => 'featured-article',
            'status' => 'published',
            'meta_description' => 'This is the featured article',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $page2 = Page::create([
            'title' => 'Article 2',
            'slug' => 'article-2',
            'status' => 'published',
            'meta_description' => 'Second article',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $page3 = Page::create([
            'title' => 'Article 3',
            'slug' => 'article-3',
            'status' => 'published',
            'meta_description' => 'Third article',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Featured Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'featured',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'featured-content'
        ]);

        $pages = collect([$featuredPage, $page2, $page3]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, $pages, null, 'test-token', true, null, $this->siteId);

        // Assert
        // Featured page should be displayed as hero
        $this->assertStringContainsString('Featured Article', $html);
        $this->assertStringContainsString('This is the featured article', $html);

        // Other pages should be in compact cards
        $this->assertStringContainsString('Article 2', $html);
        $this->assertStringContainsString('Article 3', $html);

        // Should contain unsubscribe link
        $this->assertStringContainsString('test-token', $html);
    }

    public function test_builds_simple_template_html(): void
    {
        // Arrange
        $page1 = Page::create([
            'title' => 'Simple Article 1',
            'slug' => 'simple-article-1',
            'status' => 'published',
            'meta_description' => 'Description for simple article 1',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $page2 = Page::create([
            'title' => 'Simple Article 2',
            'slug' => 'simple-article-2',
            'status' => 'published',
            'meta_description' => 'Description for simple article 2',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Simple Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'simple',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'simple-content'
        ]);

        $pages = collect([$page1, $page2]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, $pages, null, 'simple-token', true, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('Simple Newsletter', $html);
        $this->assertStringContainsString('Simple Article 1', $html);
        $this->assertStringContainsString('Simple Article 2', $html);
        $this->assertStringContainsString('Description for simple article 1', $html);
        $this->assertStringContainsString('simple-token', $html);
    }

    public function test_default_template_includes_page_images(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Article with Image',
            'slug' => 'article-with-image',
            'status' => 'published',
            'meta_description' => 'Article description',
            'listing_image_id' => 123,
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Image Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'image-content'
        ]);

        $pages = collect([$page]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, $pages, null, null, true, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('/api/media/123', $html);
        $this->assertStringContainsString('<img', $html);
    }

    public function test_default_template_includes_author_info(): void
    {
        // Arrange
        $author = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'site_id' => $this->siteId
        ]);

        $page = Page::create([
            'title' => 'Article by John',
            'slug' => 'article-by-john',
            'status' => 'published',
            'meta_description' => 'Article description',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        PageAuthor::create([
            'page_id' => $page->id,
            'author_id' => $author->id,
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Author Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'author-content'
        ]);

        // Reload page with relationships
        $page = Page::with(['authors'])->find($page->id);
        $pages = collect([$page]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, $pages, null, null, true, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('John Doe', $html);
        $this->assertStringContainsString('By', $html);
    }

    public function test_footer_includes_unsubscribe_and_manage_links(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        $pages = collect([$page]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, $pages, null, 'unique-token-123', true, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('/member/subscriptions/unsubscribe/unique-token-123', $html);
        $this->assertStringContainsString('/member/subscriptions/manage/unique-token-123', $html);
        $this->assertStringContainsString('Unsubscribe', $html);
        $this->assertStringContainsString('Manage Preferences', $html);
    }

    public function test_footer_without_unsubscribe_token(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        $pages = collect([$page]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, $pages, null, null, true, null, $this->siteId);

        // Assert
        $this->assertStringNotContainsString('/member/subscriptions/unsubscribe/', $html);
        $this->assertStringNotContainsString('Manage Preferences', $html);
        $this->assertStringContainsString('You received this email', $html);
    }

    public function test_returns_empty_collection_for_manual_newsletter(): void
    {
        // Arrange
        $newsletter = Newsletter::create([
            'title' => 'Manual Newsletter',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Manual content']]),
            'content_type' => Newsletter::CONTENT_TYPE_MANUAL,
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId
        ]);

        // Act
        $pages = $this->service->getPagesForNewsletter($newsletter, $this->siteId);

        // Assert
        $this->assertCount(0, $pages);
    }

    public function test_featured_template_handles_single_page(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Single Featured Article',
            'slug' => 'single-featured',
            'status' => 'published',
            'meta_description' => 'Only article',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Featured Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'featured',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'featured'
        ]);

        $pages = collect([$page]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, $pages, null, null, true, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('Single Featured Article', $html);
        // Should not have "More Articles" section when only one page
        $this->assertStringNotContainsString('More Articles', $html); // Section header is always present
    }

    public function test_handles_page_with_long_description(): void
    {
        // Arrange
        $longDescription = str_repeat('This is a very long description. ', 50);

        $page = Page::create([
            'title' => 'Long Description Page',
            'slug' => 'long-description',
            'status' => 'published',
            'meta_description' => $longDescription,
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        $pages = collect([$page]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, $pages, null, null, true, null, $this->siteId);
        // Assert
        // Description should be truncated to 200 characters in default template
        $this->assertStringContainsString('...', $html);
        $this->assertGreaterThan(strlen($longDescription), strlen($html));
    }

    public function test_builds_newsletter_with_text_blocks(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Article with Text',
            'slug' => 'article-with-text',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'text',
            'page_id' => $page->id,
            'data' => json_encode([
                'paragraphs' => [
                    'First paragraph of content.',
                    'Second paragraph with more details.'
                ]
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Text Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        // Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('First paragraph of content.', $html);
        $this->assertStringContainsString('Second paragraph with more details.', $html);
        $this->assertStringContainsString('<p style=', $html);
    }

    public function test_builds_newsletter_with_heading_blocks(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Article with Headings',
            'slug' => 'article-with-headings',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'heading',
            'page_id' => $page->id,
            'data' => json_encode([
                'text' => 'Main Heading',
                'subtitle' => 'Subtitle text',
                'level' => 2
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Heading Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        // Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('Main Heading', $html);
        $this->assertStringContainsString('Subtitle text', $html);
        $this->assertStringContainsString('<h2', $html);
    }

    public function test_builds_newsletter_with_image_blocks(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Article with Images',
            'slug' => 'article-with-images',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'image',
            'page_id' => $page->id,
            'data' => json_encode([
                'src' => 'https://example.com/image.jpg',
                'alt' => 'Test image',
                'caption' => 'Image caption'
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Image Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        // Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('https://example.com/image.jpg', $html);
        $this->assertStringContainsString('Test image', $html);
        $this->assertStringContainsString('Image caption', $html);
        $this->assertStringContainsString('<img', $html);
    }

    public function test_builds_newsletter_with_quote_blocks(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Article with Quotes',
            'slug' => 'article-with-quotes',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'quote',
            'page_id' => $page->id,
            'data' => json_encode([
                'text' => 'This is an inspiring quote.',
                'attribution' => 'Famous Person'
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Quote Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        // Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('This is an inspiring quote.', $html);
        $this->assertStringContainsString('Famous Person', $html);
        $this->assertStringContainsString('<blockquote', $html);
    }

    public function test_builds_newsletter_with_list_blocks(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Article with Lists',
            'slug' => 'article-with-lists',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'list',
            'page_id' => $page->id,
            'data' => json_encode([
                'listType' => 'ul',
                'items' => [
                    'First item',
                    'Second item',
                    'Third item'
                ]
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'List Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        // Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('First item', $html);
        $this->assertStringContainsString('Second item', $html);
        $this->assertStringContainsString('Third item', $html);
        $this->assertStringContainsString('<ul', $html);
        $this->assertStringContainsString('<li', $html);
    }

    public function test_builds_newsletter_with_cta_blocks(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Article with CTA',
            'slug' => 'article-with-cta',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'cta',
            'page_id' => $page->id,
            'data' => json_encode([
                'text' => 'Click Here',
                'url' => 'https://example.com/action',
                'alignment' => 'center'
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'CTA Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        // Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('Click Here', $html);
        $this->assertStringContainsString('https://example.com/action', $html);
        $this->assertStringContainsString('background-color: #007bff', $html);
    }

    public function test_builds_newsletter_with_product_blocks(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Article with Products',
            'slug' => 'article-with-products',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'product',
            'page_id' => $page->id,
            'data' => json_encode([
                'name' => 'Test Product',
                'description' => 'Product description',
                'price' => 99.99,
                'salePrice' => 79.99,
                'currency' => '$',
                'link' => 'https://example.com/product',
                'linkText' => 'Buy Now',
                'image' => ['src' => 'https://example.com/product.jpg']
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Product Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);
// Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

// Assert
        $this->assertStringContainsString('Test Product', $html);
        $this->assertStringContainsString('Product description', $html);
        $this->assertStringContainsString('$79.99', $html);
        $this->assertStringContainsString('$99.99', $html);
        $this->assertStringContainsString('Buy Now', $html);
    }

    public function test_builds_newsletter_with_table_blocks(): void
    {
// Arrange
        $page = Page::create([
            'title' => 'Article with Tables',
            'slug' => 'article-with-tables',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'table',
            'page_id' => $page->id,
            'data' => json_encode([
                'hasHeader' => true,
                'rows' => [
                    ['Header 1', 'Header 2', 'Header 3'],
                    ['Cell 1', 'Cell 2', 'Cell 3'],
                    ['Cell 4', 'Cell 5', 'Cell 6']
                ]
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Table Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

// Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

// Assert
        $this->assertStringContainsString('Header 1', $html);
        $this->assertStringContainsString('Cell 1', $html);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('<th', $html);
        $this->assertStringContainsString('<td', $html);
    }

    public function test_builds_newsletter_with_banner_blocks(): void
    {
// Arrange
        $page = Page::create([
            'title' => 'Article with Banner',
            'slug' => 'article-with-banner',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'banner',
            'page_id' => $page->id,
            'data' => json_encode([
                'title' => 'Special Offer',
                'subtitle' => 'Limited time only',
                'ctaText' => 'Get Offer',
                'ctaUrl' => 'https://example.com/offer',
                'backgroundColor' => '#ff6b6b',
                'textColor' => '#ffffff'
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Banner Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

// Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

// Assert
        $this->assertStringContainsString('Special Offer', $html);
        $this->assertStringContainsString('Limited time only', $html);
        $this->assertStringContainsString('Get Offer', $html);
        $this->assertStringContainsString('#ff6b6b', $html);
    }

    public function test_builds_newsletter_with_mixed_blocks(): void
    {
// Arrange
        $page = Page::create([
            'title' => 'Complex Article',
            'slug' => 'complex-article',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'heading',
            'page_id' => $page->id,
            'data' => json_encode([
                'type' => 'heading', 'text' => 'Introduction', 'level' => 2,
            ])
        ]);

        Block::create([
            'type' => 'text',
            'page_id' => $page->id,
            'data' => json_encode([
                'type' => 'text', 'paragraphs' => ['Opening paragraph.'],
            ])
        ]);

        Block::create([
            'type' => 'image',
            'page_id' => $page->id,
            'data' => json_encode([
                'type' => 'image', 'src' => 'https://example.com/img.jpg', 'alt' => 'Image',
            ])
        ]);

        Block::create([
            'type' => 'list',
            'page_id' => $page->id,
            'data' => json_encode([
                'type' => 'list', 'listType' => 'ul', 'items' => ['Point 1', 'Point 2'],
            ])
        ]);

        Block::create([
            'type' => 'cta',
            'page_id' => $page->id,
            'data' => json_encode([
                'type' => 'cta', 'text' => 'Learn More', 'url' => 'https://example.com'
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Mixed Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

// Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

// Assert
        $this->assertStringContainsString('Introduction', $html);
        $this->assertStringContainsString('Opening paragraph.', $html);
        $this->assertStringContainsString('https://example.com/img.jpg', $html);
        $this->assertStringContainsString('Point 1', $html);
        $this->assertStringContainsString('Learn More', $html);
    }

    public function test_handles_empty_blocks_array(): void
    {
// Arrange
        $page = Page::create([
            'title' => 'Empty Article',
            'slug' => 'empty-article',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);
        $newsletter = Newsletter::create([
            'title' => 'Empty Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

// Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

// Assert
// Should still have basic structure even with no blocks
        $this->assertStringContainsString('max-width: 600px', $html);
        $this->assertStringContainsString('background: white', $html);
    }

    public function test_sanitizes_html_in_blocks(): void
    {
// Arrange
        $page = Page::create([
            'title' => 'XSS Test Article',
            'slug' => 'xss-test',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'text',
            'page_id' => $page->id,
            'data' => json_encode([
                'paragraphs' => ['<script>alert("xss")</script>Normal text']
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'XSS Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

// Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

// Assert
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('Normal text', $html);
    }

    public function test_builds_newsletter_with_person_blocks(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Article with Person',
            'slug' => 'article-with-person',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'person',
            'page_id' => $page->id,
            'data' => json_encode([
                'name' => 'John Doe',
                'role' => 'CEO',
                'bio' => 'John is the founder and CEO.',
                'email' => 'john@example.com',
                'phone' => '+1234567890',
                'image' => ['src' => 'https://example.com/john.jpg']
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Person Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        // Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('John Doe', $html);
        $this->assertStringContainsString('CEO', $html);
        $this->assertStringContainsString('John is the founder and CEO.', $html);
        $this->assertStringContainsString('john@example.com', $html);
        $this->assertStringContainsString('+1234567890', $html);
    }

    public function test_builds_newsletter_with_product_comparison_blocks(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Article with Comparison',
            'slug' => 'article-with-comparison',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'product-comparison',
            'page_id' => $page->id,
            'data' => json_encode([
                'title' => 'Product A vs Product B',
                'productA' => 'Product A',
                'productB' => 'Product B',
                'comparisons' => [
                    [
                        'subtitle' => 'Price',
                        'items' => [
                            ['value' => '$99'],
                            ['value' => '$149']
                        ]
                    ],
                    [
                        'subtitle' => 'Features',
                        'items' => [
                            ['value' => 'Basic'],
                            ['value' => 'Advanced']
                        ]
                    ]
                ]
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Comparison Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        // Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('Product A vs Product B', $html);
        $this->assertStringContainsString('Product A', $html);
        $this->assertStringContainsString('Product B', $html);
        $this->assertStringContainsString('Price', $html);
        $this->assertStringContainsString('$99', $html);
        $this->assertStringContainsString('$149', $html);
        $this->assertStringContainsString('<table', $html);
    }

    public function test_builds_newsletter_with_schema_question_blocks(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Article with FAQ',
            'slug' => 'article-with-faq',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'schema',
            'page_id' => $page->id,
            'data' => json_encode([
                'schemaType' => 'question',
                'question' => 'What is the return policy?',
                'answer' => 'We offer 30-day returns.',
                'expansion' => 'Items must be in original condition.'
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'FAQ Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        // Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('What is the return policy?', $html);
        $this->assertStringContainsString('We offer 30-day returns.', $html);
        $this->assertStringContainsString('Items must be in original condition.', $html);
    }

    public function test_builds_newsletter_with_schema_howto_blocks(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Article with How-To',
            'slug' => 'article-with-howto',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'schema',
            'page_id' => $page->id,
            'data' => json_encode([
                'schemaType' => 'how-to',
                'title' => 'How to Install',
                'description' => 'Follow these simple steps.',
                'image' => ['src' => 'https://example.com/howto.jpg']
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'How-To Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        // Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('How to Install', $html);
        $this->assertStringContainsString('Follow these simple steps.', $html);
        $this->assertStringContainsString('https://example.com/howto.jpg', $html);
    }

    public function test_builds_newsletter_with_stats_blocks(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Article with Stats',
            'slug' => 'article-with-stats',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'stats',
            'page_id' => $page->id,
            'data' => json_encode([
                'title' => 'Our Achievements',
                'stats' => [
                    [
                        'number' => '10K+',
                        'label' => 'Customers',
                        'description' => 'Happy clients worldwide',
                        'icon' => '👥'
                    ],
                    [
                        'number' => '99%',
                        'label' => 'Satisfaction',
                        'description' => 'Customer satisfaction rate',
                        'icon' => '⭐'
                    ],
                    [
                        'number' => '24/7',
                        'label' => 'Support',
                        'description' => 'Always here to help',
                        'icon' => '🛟'
                    ]
                ]
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Stats Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        // Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('Our Achievements', $html);
        $this->assertStringContainsString('10K+', $html);
        $this->assertStringContainsString('Customers', $html);
        $this->assertStringContainsString('99%', $html);
        $this->assertStringContainsString('Satisfaction', $html);
        $this->assertStringContainsString('24/7', $html);
        $this->assertStringContainsString('Support', $html);
    }

    public function test_builds_newsletter_with_testimonial_blocks(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Article with Testimonials',
            'slug' => 'article-with-testimonials',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'testimonial',
            'page_id' => $page->id,
            'data' => json_encode([
                'testimonials' => [
                    [
                        'text' => 'Great service and excellent support!',
                        'author' => 'Jane Smith',
                        'role' => 'Marketing Director',
                        'rating' => 5,
                        'image' => ['src' => 'https://example.com/jane.jpg']
                    ],
                    [
                        'text' => 'Highly recommend to everyone.',
                        'author' => 'Bob Johnson',
                        'role' => 'CEO',
                        'rating' => 5,
                        'image' => ['src' => 'https://example.com/bob.jpg']
                    ]
                ]
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Testimonial Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        // Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('What Our Clients Say', $html);
        $this->assertStringContainsString('Great service and excellent support!', $html);
        $this->assertStringContainsString('Jane Smith', $html);
        $this->assertStringContainsString('Marketing Director', $html);
        $this->assertStringContainsString('Highly recommend to everyone.', $html);
        $this->assertStringContainsString('Bob Johnson', $html);
        $this->assertStringContainsString('⭐', $html);
    }

    public function test_person_block_without_image(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Person Without Image',
            'slug' => 'person-no-image',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'person',
            'page_id' => $page->id,
            'data' => json_encode([
                'name' => 'Jane Doe',
                'role' => 'Designer',
                'bio' => 'Creative designer with 10 years experience.',
                'email' => 'jane@example.com'
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        // Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('Jane Doe', $html);
        $this->assertStringContainsString('Designer', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_stats_block_with_multiple_stats(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Multiple Stats',
            'slug' => 'multiple-stats',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'stats',
            'page_id' => $page->id,
            'data' => json_encode([
                'stats' => [
                    ['number' => '1M', 'label' => 'Users'],
                    ['number' => '50K', 'label' => 'Products'],
                    ['number' => '100+', 'label' => 'Countries'],
                    ['number' => '5★', 'label' => 'Rating']
                ]
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Multi Stats Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        // Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('1M', $html);
        $this->assertStringContainsString('Users', $html);
        $this->assertStringContainsString('50K', $html);
        $this->assertStringContainsString('Products', $html);
        $this->assertStringContainsString('100+', $html);
        $this->assertStringContainsString('Countries', $html);
    }

    public function test_builds_newsletter_with_award_blocks(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Article with Award',
            'slug' => 'article-with-award',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'award',
            'page_id' => $page->id,
            'data' => json_encode([
                'subcategory' => 'Best Product 2024',
                'productName' => 'Amazing Product',
                'caption' => 'Outstanding performance and quality.',
                'strapline' => 'Editor\'s Choice',
                'rating' => 5,
                'winner' => true,
                'image' => ['src' => 'https://example.com/award.jpg']
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Award Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        // Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('Best Product 2024', $html);
        $this->assertStringContainsString('Amazing Product', $html);
        $this->assertStringContainsString('Outstanding performance and quality.', $html);
        $this->assertStringContainsString('Winner', $html);
        $this->assertStringContainsString('#FFD700', $html);
    }

    public function test_builds_newsletter_with_boxout_blocks(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Article with Boxout',
            'slug' => 'article-with-boxout',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'note',
            'page_id' => $page->id,
            'data' => json_encode([
                'title' => 'Important Note',
                'paragraphs' => [
                    'This is the first paragraph.',
                    'This is the second paragraph.'
                ],
                'linkUrl' => 'https://example.com/more',
                'linkText' => 'Read More',
                'sponsored' => true,
                'image' => ['src' => 'https://example.com/note.jpg']
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Boxout Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        // Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('Important Note', $html);
        $this->assertStringContainsString('This is the first paragraph.', $html);
        $this->assertStringContainsString('This is the second paragraph.', $html);
        $this->assertStringContainsString('Read More', $html);
        $this->assertStringContainsString('Sponsored', $html);
    }

    public function test_builds_newsletter_with_buying_guide_blocks(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Article with Buying Guide',
            'slug' => 'article-with-buying-guide',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'buying-guide',
            'page_id' => $page->id,
            'data' => json_encode([
                'title' => 'Ultimate Laptop Guide',
                'subtitle' => 'Everything you need to know',
                'specs' => [
                    ['text' => 'Processor', 'value' => 'Intel Core i7'],
                    ['text' => 'RAM', 'value' => '16GB'],
                    ['text' => 'Storage', 'value' => '512GB SSD']
                ],
                'pros' => ['Fast performance', 'Great battery life'],
                'cons' => ['Expensive', 'Heavy'],
                'showReviewPanel' => true,
                'url' => 'https://example.com/buy',
                'linkText' => 'Buy Now',
                'image' => ['src' => 'https://example.com/laptop.jpg']
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Buying Guide Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        // Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('Ultimate Laptop Guide', $html);
        $this->assertStringContainsString('Everything you need to know', $html);
        $this->assertStringContainsString('Processor', $html);
        $this->assertStringContainsString('Intel Core i7', $html);
        $this->assertStringContainsString('Fast performance', $html);
        $this->assertStringContainsString('Expensive', $html);
        $this->assertStringContainsString('Advantages', $html);
        $this->assertStringContainsString('Considerations', $html);
    }

    public function test_builds_newsletter_with_contact_form_blocks(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Article with Contact',
            'slug' => 'article-with-contact',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'contact-form',
            'page_id' => $page->id,
            'data' => json_encode([
                'title' => 'Get In Touch',
                'subtitle' => 'We would love to hear from you',
                'contact_info' => [
                    'email' => 'hello@example.com',
                    'phone' => '+44 20 1234 5678',
                    'address' => [
                        'line1' => '123 High Street',
                        'line2' => 'Suite 100',
                        'city' => 'London',
                        'postcode' => 'SW1A 1AA'
                    ]
                ]
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Contact Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        // Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('Get In Touch', $html);
        $this->assertStringContainsString('We would love to hear from you', $html);
        $this->assertStringContainsString('hello@example.com', $html);
        $this->assertStringContainsString('+44 20 1234 5678', $html);
        $this->assertStringContainsString('123 High Street', $html);
        $this->assertStringContainsString('London', $html);
    }

    public function test_builds_newsletter_with_deal_blocks(): void
    {
        // Arrange
        $page = Page::create([
            'title' => 'Article with Deal',
            'slug' => 'article-with-deal',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'deal',
            'page_id' => $page->id,
            'data' => json_encode([
                'title' => 'Black Friday Deal',
                'productName' => 'Wireless Headphones',
                'brand' => 'TechBrand',
                'description' => 'Premium noise-cancelling headphones',
                'price' => 299.99,
                'salePrice' => 199.99,
                'currency' => '£',
                'savings' => 100,
                'savings_percent' => 33,
                'has_savings' => true,
                'voucherId' => 'SAVE100',
                'link' => 'https://example.com/deal',
                'sponsored' => true,
                'image' => ['src' => 'https://example.com/headphones.jpg']
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Deal Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

        // Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        // Assert
        $this->assertStringContainsString('Black Friday Deal', $html);
        $this->assertStringContainsString('Wireless Headphones', $html);
        $this->assertStringContainsString('TechBrand', $html);
        $this->assertStringContainsString('£199.99', $html);
        $this->assertStringContainsString('£299.99', $html);
        $this->assertStringContainsString('Save £100', $html);
        $this->assertStringContainsString('33%', $html);
        $this->assertStringContainsString('SAVE100', $html);
        $this->assertStringContainsString('Sponsored', $html);
        $this->assertStringContainsString('Get Deal', $html);
    }

    public function test_award_block_without_winner_badge(): void
    {
// Arrange
        $page = Page::create([
            'title' => 'Non-Winner Award',
            'slug' => 'non-winner-award',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'award',
            'page_id' => $page->id,
            'data' => json_encode([
                'subcategory' => 'Honorable Mention',
                'productName' => 'Good Product',
                'winner' => false,
                'rating' => 4
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

// Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

// Assert
        $this->assertStringContainsString('Good Product', $html);
        $this->assertStringNotContainsString('Winner', $html);
        $this->assertStringNotContainsString('#FFD700', $html);
    }

    public function test_deal_block_without_voucher(): void
    {
// Arrange
        $page = Page::create([
            'title' => 'Deal Without Voucher',
            'slug' => 'deal-no-voucher',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId
        ]);

        Block::create([
            'type' => 'deal',
            'page_id' => $page->id,
            'data' => json_encode([
                'title' => 'Simple Deal',
                'productName' => 'Product',
                'price' => 50,
                'salePrice' => 40,
                'currency' => '$',
                'link' => 'https://example.com'
            ])
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'template' => 'default',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);

// Act
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

// Assert
        $this->assertStringContainsString('Simple Deal', $html);
        $this->assertStringContainsString('$40', $html);
        $this->assertStringNotContainsString('Use Code:', $html);
    }

    public function testBuildNewsletterHtmlIncludesTrackingWithSendId(): void
    {
        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'site_id' => $this->siteId,
            'active' => true,
            'interval' => 'weekly',
            'template' => 'default',
            'content' => 'test'
        ]);

        $page = new Page([
            'id' => 1,
            'title' => 'Test Page',
            'slug' => 'test-page',
            'site_id' => $this->siteId,
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'subtitle' => 'Test subtitle'
        ]);

        $pages = collect([$page]);

        $sendId = 123;
        $html = $this->service->buildNewsletterHtml($newsletter, $pages, null, null, false, $sendId, $this->siteId);
        $html = urldecode($html);

        // Should contain tracking URL with placeholders
        $this->assertStringContainsString('/newsletters/track-view', $html);
        $this->assertStringContainsString('send_id={{SEND_ID}}', $html);
        $this->assertStringContainsString('e={{TRACKING_EMAIL}}', $html);
        $this->assertStringContainsString('page_id=1', $html);
    }

//    public function testBuildNewsletterHtmlWithoutSendIdUsesDirectLinks(): void
//    {
//        $newsletter = Newsletter::create([
//            'title' => 'Test Newsletter',
//            'site_id' => $this->siteId,
//            'active' => true,
//            'interval' => 'weekly',
//            'template' => 'default',
//            'content' => 'test'
//        ]);
//
//        $page = new Page([
//            'id' => 1,
//            'slug' => 'test-page',
//            'title' => 'Test Page',
//            'subtitle' => 'Test Subtitle'
//        ]);
//
//        $pages = collect([$page]);
//
//        $html = $this->service->buildNewsletterHtml($newsletter, $pages, null, false, null);
//
//        // Should contain direct URLs, not tracking URLs
//        $this->assertStringNotContainsString('/newsletters/track-view', $html);
//        $this->assertStringContainsString('/test-page', $html);
//    }

    public function testDigestTemplateIncludesTracking(): void
    {
        $newsletter = Newsletter::create([
            'title' => 'Digest Newsletter',
            'site_id' => $this->siteId,
            'active' => true,
            'interval' => 'weekly',
            'template' => 'digest',
            'content' => 'test'
        ]);

        $page = new Page([
            'id' => 5,
            'title' => 'Digest Item',
            'subtitle' => 'Subtitle',
            'slug' => 'digest-item'
        ]);

        $pages = collect([$page]);

        $sendId = 456;
        $html = $this->service->buildNewsletterHtml($newsletter, $pages, null, null, false, $sendId, $this->siteId);
        $html = urldecode($html);

        $this->assertStringContainsString('/newsletters/track-view', $html);
        $this->assertStringContainsString('page_id=5', $html);
        $this->assertStringContainsString('{{SEND_ID}}', $html);
        $this->assertStringContainsString('{{TRACKING_EMAIL}}', $html);
    }

    public function testFeaturedTemplateIncludesTracking(): void
    {
        $newsletter = Newsletter::create([
            'title' => 'Featured Newsletter',
            'site_id' => $this->siteId,
            'active' => true,
            'interval' => 'weekly',
            'template' => 'featured',
            'content' => 'test'
        ]);

        $page = new Page([
            'id' => 10,
            'title' => 'Featured Page',
            'slug' => 'featured-page',
            'subtitle' => 'Test Subtitle'
        ]);

        $pages = collect([$page]);

        $sendId = 789;
        $html = $this->service->buildNewsletterHtml($newsletter, $pages, null, false, true, null, $this->siteId);
        $html = urldecode($html);

        $this->assertStringContainsString('/newsletters/track-view', $html);
        $this->assertStringContainsString('page_id=10', $html);
    }

    public function testSuppressesInactiveOffer(): void
    {
        $product = $this->createProduct();
        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => false,
        ]);

        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
        ]);

        Block::create([
            'type' => 'offer',
            'page_id' => $page->id,
            'data' => json_encode(['offer_id' => $offer->id]),
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'site_id' => $this->siteId,
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'active' => true,
            'interval' => 'weekly',
            'content' => 'test',
        ]);

        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        $this->assertStringNotContainsString('Partner Offer', $html);
        $this->assertStringNotContainsString($product->name, $html);
    }

    public function testSuppressesRewardForUnauthenticated(): void
    {
        $member = $this->createMember();
        $reward = $this->createMemberReward([
            'member_id' => $member->id,
            'status' => 'pending',
        ]);

        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
        ]);

        Block::create([
            'type' => 'reward',
            'page_id' => $page->id,
            'data' => json_encode(['reward_id' => $reward->id]),
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'site_id' => $this->siteId,
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'active' => true,
            'interval' => 'weekly',
            'content' => 'test',
        ]);

        // PASS NULL FOR MEMBER
        $html = $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        $this->assertStringNotContainsString('Member Reward', $html);
    }

    public function testTracksOfferRender(): void
    {
        $product = $this->createProduct();

        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
        ]);

        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
        ]);

        Block::create([
            'type' => 'offer',
            'page_id' => $page->id,
            'data' => json_encode(['offer_id' => $offer->id]),
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'site_id' => $this->siteId,
            'content_type' => Newsletter::CONTENT_TYPE_AUTO_PAGES,
            'active' => true,
            'interval' => 'weekly',
            'content' => 'test',
        ]);

        $this->service->buildNewsletterHtmlFromBlocks($newsletter, $page, null, null, $this->siteId);

        // Should have tracked render
        $this->assertDatabaseHas('offer_clicks', [
            'offer_id' => $offer->id,
            'action' => 'render',
        ]);
    }

    public function test_newsletter_html_includes_site_logo_url(): void
    {
        // Arrange
        $site = Site::create([
            'name' => 'Test Site',
            'logo' => 'https://example.com/logo.png',
            'slug' => 'test-site',
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'content' => '[]',
            'site_id' => $site->id,
            'active' => true,
            'interval' => Newsletter::INTERVAL_WEEKLY
        ]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, collect([]), null, false, true, null, $site->id);


        // Assert
        $this->assertStringContainsString('https://example.com/logo.png', $html);
        $this->assertStringContainsString('alt="Test Site"', $html);
    }

    public function test_newsletter_html_includes_logo_from_image(): void
    {
        // Arrange
        $image = $this->createImage(['file_path' => '/images/logo.png']);

        $site = Site::create([
            'name' => 'Test Site',
            'logo_image_id' => $image->id
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'content' => '[]',
            'site_id' => $site->id,
            'active' => true,
            'interval' => Newsletter::INTERVAL_WEEKLY
        ]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, collect([]), null, false, true, null, $site->id);

        // Assert
        $this->assertStringContainsString('/images/test.jpg', $html);
    }

    public function test_newsletter_html_uses_placeholder_when_no_logo(): void
    {
        // Arrange
        $site = Site::create([
            'name' => 'Test Site'
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'content' => '[]',
            'site_id' => $site->id,
            'active' => true,
            'interval' => Newsletter::INTERVAL_WEEKLY
        ]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, collect([]), null, false, true, null, $site->id);

        // Assert
        $this->assertStringContainsString('https://via.placeholder.com/200x60?text=Logo', $html);
    }

    public function test_newsletter_html_prefers_logo_url_over_image(): void
    {
        // Arrange
        $image = $this->createImage([
            'file_path' => '/images/logo.png',
            'site_id' => $this->siteId
        ]);

        $site = Site::create([
            'name' => 'Test Site',
            'logo' => 'https://example.com/priority-logo.png',
            'logo_image_id' => $image->id
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'content' => '[]',
            'site_id' => $site->id,
            'active' => true,
            'interval' => Newsletter::INTERVAL_WEEKLY
        ]);

        // Act
        $html = $this->service->buildNewsletterHtml($newsletter, collect([]), null, false, true, null, $site->id);

        // Assert
        $this->assertStringContainsString('https://example.com/priority-logo.png', $html);
        $this->assertStringNotContainsString('/images/logo.png', $html);
    }

}
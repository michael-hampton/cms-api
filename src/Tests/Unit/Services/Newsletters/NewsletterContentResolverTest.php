<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\DTO\Newsletters\Layout\LayoutRegionValueObject;
use App\DTO\Newsletters\NewsletterResolveResult;
use App\Enums\Newsletters\ContentSourceType;
use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Models\Member;
use App\Models\Newsletter;
use App\Models\NewsletterBrandingConfiguration;
use App\Models\NewsletterLayoutVersion;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\Layout\LayoutRenderPipeline;
use App\Services\Newsletter\NewsletterContentResolver;
use App\Services\Newsletter\NewsletterPageBuilderService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

/**
 * Unit tests for NewsletterContentResolver.
 *
 * Tests the dispatch logic only — no HTML assertion, no DB.
 * All dependencies are mocked; rendering correctness is tested
 * in their own test classes.
 *
 * Matrix:
 *   Content type × Layout version (none / v1 / v2)
 *   + edge cases (empty blocks, empty legacy content, empty pages)
 */
class NewsletterContentResolverTest extends FunctionalTestCase
{
    private NewsletterPageBuilderService|MockInterface $pageBuilder;
    private LayoutRenderPipeline|MockInterface $renderPipeline;
    private Logger|MockInterface $logger;
    private NewsletterContentResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pageBuilder = Mockery::mock(NewsletterPageBuilderService::class);
        $this->renderPipeline = Mockery::mock(LayoutRenderPipeline::class);
        $this->logger = Mockery::mock(Logger::class);

        $this->resolver = new NewsletterContentResolver(
            $this->pageBuilder,
            $this->renderPipeline,
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // Custom Blocks — no layout
    // =========================================================================

    public function test_custom_blocks_no_layout_routes_to_slot_renderer(): void
    {
        $blocks = [['type' => 'text', 'data' => ['paragraphs' => ['Hello']]]];
        $newsletter = $this->makeNewsletter(ContentSourceType::CustomBlocks);
        $newsletter->content_blocks = $blocks;

        $this->pageBuilder
            ->shouldReceive('buildNewsletterHtmlFromLayoutSlots')
            ->once()
            ->withArgs(function (Newsletter $nl, array $payload) {
                $slots = $payload['slots'];
                return count($slots) === 1
                    && $slots[0]['key'] === 'content'
                    && $slots[0]['blocks'][0]['type'] === 'text';
            })
            ->andReturn('<html>custom</html>');

        $result = $this->resolver->resolve($newsletter, siteId: 1);

        $this->assertInstanceOf(NewsletterResolveResult::class, $result);
        $this->assertSame('<html>custom</html>', $result->html);
        $this->assertNull($result->pages);
    }

    public function test_custom_blocks_empty_returns_empty_string_without_rendering(): void
    {
        $newsletter = $this->makeNewsletter(ContentSourceType::CustomBlocks);
        $newsletter->content_blocks = [];

        $this->pageBuilder->shouldNotReceive('buildNewsletterHtmlFromLayoutSlots');
        $this->renderPipeline->shouldNotReceive('renderBody');

        $result = $this->resolver->resolve($newsletter, siteId: 1);

        $this->assertSame('', $result->html);
        $this->assertNull($result->pages);
    }

    // =========================================================================
    // Custom Blocks — v1 layout
    // =========================================================================

    public function test_custom_blocks_v1_layout_routes_to_slot_renderer(): void
    {
        $newsletter = $this->makeNewsletter(ContentSourceType::CustomBlocks);
        $newsletter->content_blocks = [
            ['type' => 'heading', 'data' => ['text' => 'Title', 'level' => 1]],
        ];
        $layoutVersion = $this->makeLayoutVersion(schemaVersion: 1);

        $this->pageBuilder
            ->shouldReceive('buildNewsletterHtmlFromLayoutSlots')
            ->once()
            ->andReturn('<html>v1-custom</html>');

        $result = $this->resolver->resolve($newsletter, siteId: 1, layoutVersion: $layoutVersion);

        $this->assertSame('<html>v1-custom</html>', $result->html);
        $this->assertNull($result->pages);
    }


    // =========================================================================
    // Custom Blocks — v2 layout
    // =========================================================================

    public function test_custom_blocks_v2_layout_routes_to_region_pipeline(): void
    {
        $newsletter = $this->makeNewsletter(ContentSourceType::CustomBlocks);
        $layoutVersion = $this->makeLayoutVersion(schemaVersion: 2);
        $blocks = [['type' => 'text', 'data' => ['paragraphs' => ['Hello']]]];
        $newsletter->content_blocks = $blocks;

        $this->renderPipeline
            ->shouldReceive('renderBody')
            ->once()
            ->withArgs(function (LayoutRegionValueObject $layout, NewsletterRenderContext $ctx) {
                $center = $layout->getCenterRegion();
                return $center !== null && count($center->slots) === 1;
            })
            ->andReturn('<body>v2 regions</body>');

        $this->pageBuilder
            ->shouldReceive('buildTemplate')
            ->once()
            ->andReturn('<html>v2-custom</html>');

        $result = $this->resolver->resolve($newsletter, siteId: 1, layoutVersion: $layoutVersion);

        $this->assertSame('<html>v2-custom</html>', $result->html);
        $this->assertNull($result->pages);
    }

    public function test_custom_blocks_v2_never_calls_slot_renderer(): void
    {
        $newsletter = $this->makeNewsletter(ContentSourceType::CustomBlocks);
        $layoutVersion = $this->makeLayoutVersion(schemaVersion: 2);
        $newsletter->content_blocks = [
            ['type' => 'text', 'data' => ['paragraphs' => ['x']]],
        ];

        $this->renderPipeline->shouldReceive('renderBody')->once()->andReturn('');
        $this->pageBuilder->shouldReceive('buildTemplate')->once()->andReturn('');
        $this->pageBuilder->shouldNotReceive('buildNewsletterHtmlFromLayoutSlots');

        $this->resolver->resolve($newsletter, siteId: 1, layoutVersion: $layoutVersion);

        // Mockery enforces shouldNotReceive — reaching here means the constraint held.
        $this->assertTrue(true);
    }

    // =========================================================================
    // Auto Pages — no layout
    // =========================================================================

    public function test_auto_pages_no_layout_routes_to_page_builder(): void
    {
        $newsletter = $this->makeNewsletter(ContentSourceType::AutoPages);
        $pages = collect([
            (object)['id' => 1, 'title' => 'Page 1', 'subtitle' => 'Sub', 'slug' => 'page-1'],
        ]);

        $this->pageBuilder->shouldReceive('getPagesForNewsletter')->once()->andReturn($pages);
        $this->pageBuilder
            ->shouldReceive('buildNewsletterHtml')
            ->once()
            ->withArgs(function (Newsletter $nl, Collection $p) use ($pages) {
                return $p === $pages;
            })
            ->andReturn('<html>auto-no-layout</html>');

        $result = $this->resolver->resolve($newsletter, siteId: 1);

        $this->assertSame('<html>auto-no-layout</html>', $result->html);
        $this->assertNotNull($result->pages);
        $this->assertCount(1, $result->pages);
        $this->assertSame('Page 1', $result->pages[0]['title']);
    }

    public function test_auto_pages_no_layout_maps_pages_correctly(): void
    {
        $newsletter = $this->makeNewsletter(ContentSourceType::AutoPages);
        $pages = collect([
            (object)['id' => 7, 'title' => 'My Page', 'subtitle' => 'My Sub', 'slug' => 'my-page'],
        ]);

        $this->pageBuilder->shouldReceive('getPagesForNewsletter')->andReturn($pages);
        $this->pageBuilder->shouldReceive('buildNewsletterHtml')->andReturn('');

        $result = $this->resolver->resolve($newsletter, siteId: 1);

        $this->assertSame([
            ['id' => 7, 'title' => 'My Page', 'subtitle' => 'My Sub', 'slug' => 'my-page'],
        ], $result->pages);
    }

    public function test_auto_pages_returns_empty_pages_array_when_no_pages_found(): void
    {
        $newsletter = $this->makeNewsletter(ContentSourceType::AutoPages);

        $this->pageBuilder->shouldReceive('getPagesForNewsletter')->andReturn(collect([]));
        $this->pageBuilder->shouldReceive('buildNewsletterHtml')->andReturn('');

        $result = $this->resolver->resolve($newsletter, siteId: 1);

        $this->assertNotNull($result->pages);
        $this->assertSame([], $result->pages);
    }

    // =========================================================================
    // Auto Pages — v1 layout
    // =========================================================================

    public function test_auto_pages_v1_layout_routes_to_page_builder(): void
    {
        $newsletter = $this->makeNewsletter(ContentSourceType::AutoPages);
        $layoutVersion = $this->makeLayoutVersion(schemaVersion: 1);
        $pages = collect([]);

        $this->pageBuilder->shouldReceive('getPagesForNewsletter')->once()->andReturn($pages);
        $this->pageBuilder
            ->shouldReceive('buildNewsletterHtml')
            ->once()
            ->andReturn('<html>auto-v1</html>');

        $result = $this->resolver->resolve($newsletter, siteId: 1, layoutVersion: $layoutVersion);

        $this->assertSame('<html>auto-v1</html>', $result->html);
        $this->assertNotNull($result->pages);
    }

    // =========================================================================
    // Auto Pages — v2 layout
    // =========================================================================

    public function test_auto_pages_v2_layout_calls_convert_pages_to_blocks(): void
    {
        $newsletter = $this->makeNewsletter(ContentSourceType::AutoPages);
        $layoutVersion = $this->makeLayoutVersion(schemaVersion: 2);
        $pages = collect([(object)['id' => 1, 'title' => 'Page 1', 'subtitle' => 'Sub', 'slug' => 'page-1']]);
        $blocks = [['type' => 'page_card', 'data' => ['id' => 1]]];

        $this->pageBuilder->shouldReceive('getPagesForNewsletter')->once()->andReturn($pages);
        $this->pageBuilder
            ->shouldReceive('convertPagesToBlocks')
            ->once()
            ->withArgs(function (Collection $p, Newsletter $nl, int $siteId) use ($pages) {
                return $p === $pages && $siteId === 1;
            })
            ->andReturn($blocks);

        $this->renderPipeline->shouldReceive('renderBody')->once()->andReturn('<body>regions</body>');
        $this->pageBuilder->shouldReceive('buildTemplate')->once()->andReturn('<html>auto-v2</html>');

        $result = $this->resolver->resolve($newsletter, siteId: 1, layoutVersion: $layoutVersion);

        $this->assertSame('<html>auto-v2</html>', $result->html);
        $this->assertNotNull($result->pages);
        $this->assertCount(1, $result->pages);
    }

    public function test_auto_pages_v2_pages_fetched_only_once(): void
    {
        // getPagesForNewsletter must be called exactly once even though it feeds
        // both convertPagesToBlocks and the returned pages DTO.
        $newsletter = $this->makeNewsletter(ContentSourceType::AutoPages);
        $layoutVersion = $this->makeLayoutVersion(schemaVersion: 2);

        $this->pageBuilder->shouldReceive('getPagesForNewsletter')->once()->andReturn(collect([]));
        $this->pageBuilder->shouldReceive('convertPagesToBlocks')->andReturn([]);
        $this->renderPipeline->shouldReceive('renderBody')->andReturn('');
        $this->pageBuilder->shouldReceive('buildTemplate')->andReturn('');

        $this->resolver->resolve($newsletter, siteId: 1, layoutVersion: $layoutVersion);

        $this->assertTrue(true); // Mockery once() enforces the constraint.
    }

    public function test_auto_pages_v2_passes_send_id_to_convert(): void
    {
        $newsletter = $this->makeNewsletter(ContentSourceType::AutoPages);
        $layoutVersion = $this->makeLayoutVersion(schemaVersion: 2);
        $pages = collect([(object)['id' => 1, 'title' => 'P', 'subtitle' => '', 'slug' => 's']]);

        $this->pageBuilder->shouldReceive('getPagesForNewsletter')->andReturn($pages);
        $this->pageBuilder
            ->shouldReceive('convertPagesToBlocks')
            ->once()
            ->withArgs(function (Collection $p, Newsletter $nl, int $siteId, ?Member $member, ?int $sendId) {
                return $sendId === 42;
            })
            ->andReturn([]);

        $this->renderPipeline->shouldReceive('renderBody')->andReturn('');
        $this->pageBuilder->shouldReceive('buildTemplate')->andReturn('');

        $this->resolver->resolve($newsletter, siteId: 1, sendId: 42, layoutVersion: $layoutVersion);
        $this->assertTrue(true);
    }

    public function test_auto_pages_v2_never_calls_buildNewsletterHtml(): void
    {
        $newsletter = $this->makeNewsletter(ContentSourceType::AutoPages);
        $layoutVersion = $this->makeLayoutVersion(schemaVersion: 2);

        $this->pageBuilder->shouldReceive('getPagesForNewsletter')->andReturn(collect([]));
        $this->pageBuilder->shouldReceive('convertPagesToBlocks')->andReturn([]);
        $this->pageBuilder->shouldNotReceive('buildNewsletterHtml');
        $this->renderPipeline->shouldReceive('renderBody')->andReturn('');
        $this->pageBuilder->shouldReceive('buildTemplate')->andReturn('');

        $this->resolver->resolve($newsletter, siteId: 1, layoutVersion: $layoutVersion);

        // If we reach here without Mockery throwing, buildNewsletterHtml was never called.
        $this->assertTrue(true);
    }

    // =========================================================================
    // Legacy (manual) content — no layout
    // =========================================================================

    public function test_legacy_content_no_layout_wraps_as_text_block(): void
    {
        $newsletter = $this->makeNewsletter(ContentSourceType::Manual);
        $newsletter->legacy_content = 'My old newsletter text.';

        $this->pageBuilder
            ->shouldReceive('buildNewsletterHtmlFromLayoutSlots')
            ->once()
            ->withArgs(function (Newsletter $nl, array $payload) {
                $slots = $payload['slots'];
                return $slots[0]['key'] === 'content'
                    && $slots[0]['blocks'][0]['type'] === 'text';
            })
            ->andReturn('<html>legacy</html>');

        $result = $this->resolver->resolve($newsletter, siteId: 1);

        $this->assertSame('<html>legacy</html>', $result->html);
        $this->assertNull($result->pages);
    }

    public function test_legacy_falls_back_to_content_column_when_no_legacy_content(): void
    {
        $newsletter = $this->makeNewsletter(ContentSourceType::Manual);
        $newsletter->legacy_content = null;
        $newsletter->content = 'Fallback column text.';

        $this->pageBuilder
            ->shouldReceive('buildNewsletterHtmlFromLayoutSlots')
            ->once()
            ->withArgs(function (Newsletter $nl, array $payload) {
                return str_contains($payload['slots'][0]['blocks'][0]['data']['paragraphs'][0], 'Fallback');
            })
            ->andReturn('<html>fallback</html>');

        $result = $this->resolver->resolve($newsletter, siteId: 1);

        $this->assertSame('<html>fallback</html>', $result->html);
        $this->assertNull($result->pages);
    }

    public function test_legacy_empty_content_returns_empty_string(): void
    {
        $newsletter = $this->makeNewsletter(ContentSourceType::Manual);
        $newsletter->legacy_content = '';
        $newsletter->content = '';

        $this->pageBuilder->shouldNotReceive('buildNewsletterHtmlFromLayoutSlots');
        $this->renderPipeline->shouldNotReceive('renderBody');

        $result = $this->resolver->resolve($newsletter, siteId: 1);

        $this->assertSame('', $result->html);
        $this->assertNull($result->pages);
    }

    public function test_legacy_whitespace_only_returns_empty_string(): void
    {
        $newsletter = $this->makeNewsletter(ContentSourceType::Manual);
        $newsletter->legacy_content = '   ';

        $this->pageBuilder->shouldNotReceive('buildNewsletterHtmlFromLayoutSlots');

        $result = $this->resolver->resolve($newsletter, siteId: 1);

        $this->assertSame('', $result->html);
        $this->assertNull($result->pages);
    }

    // =========================================================================
    // Legacy content — v2 layout
    // =========================================================================

    public function test_legacy_v2_layout_routes_to_region_pipeline(): void
    {
        $newsletter = $this->makeNewsletter(ContentSourceType::Manual);
        $layoutVersion = $this->makeLayoutVersion(schemaVersion: 2);
        $newsletter->legacy_content = 'Old text.';

        $this->renderPipeline->shouldReceive('renderBody')->once()->andReturn('<body>regions</body>');
        $this->pageBuilder->shouldReceive('buildTemplate')->once()->andReturn('<html>legacy-v2</html>');
        $this->pageBuilder->shouldNotReceive('buildNewsletterHtmlFromLayoutSlots');

        $result = $this->resolver->resolve($newsletter, siteId: 1, layoutVersion: $layoutVersion);

        $this->assertSame('<html>legacy-v2</html>', $result->html);
        $this->assertNull($result->pages);
    }

    // =========================================================================
    // Context propagation
    // =========================================================================

    public function test_v2_render_context_carries_correct_site_and_newsletter(): void
    {
        $newsletter = $this->makeNewsletter(ContentSourceType::CustomBlocks);
        $layoutVersion = $this->makeLayoutVersion(schemaVersion: 2);
        $newsletter->content_blocks = [['type' => 'text', 'data' => []]];

        $this->renderPipeline
            ->shouldReceive('renderBody')
            ->once()
            ->withArgs(function (LayoutRegionValueObject $layout, NewsletterRenderContext $ctx) {
                return $ctx->siteId === 7 && $ctx->newsletter->id === 99;
            })
            ->andReturn('');

        $this->pageBuilder->shouldReceive('buildTemplate')->andReturn('');

        $this->resolver->resolve($newsletter, siteId: 7, layoutVersion: $layoutVersion);
        $this->assertTrue(true);
    }

    public function test_v2_render_context_sets_include_tracking_when_send_id_present(): void
    {
        $newsletter = $this->makeNewsletter(ContentSourceType::CustomBlocks);
        $layoutVersion = $this->makeLayoutVersion(schemaVersion: 2);
        $newsletter->content_blocks = [['type' => 'text', 'data' => []]];

        $this->renderPipeline
            ->shouldReceive('renderBody')
            ->once()
            ->withArgs(function (LayoutRegionValueObject $layout, NewsletterRenderContext $ctx) {
                return $ctx->sendId === 55 && $ctx->includeTracking === true;
            })
            ->andReturn('');

        $this->pageBuilder->shouldReceive('buildTemplate')->andReturn('');

        $this->resolver->resolve($newsletter, siteId: 1, sendId: 55, layoutVersion: $layoutVersion);
        $this->assertTrue(true);
    }

    public function test_v2_render_context_no_tracking_without_send_id(): void
    {
        $newsletter = $this->makeNewsletter(ContentSourceType::CustomBlocks);
        $layoutVersion = $this->makeLayoutVersion(schemaVersion: 2);
        $newsletter->content_blocks = [['type' => 'text', 'data' => []]];

        $this->renderPipeline
            ->shouldReceive('renderBody')
            ->once()
            ->withArgs(function (LayoutRegionValueObject $layout, NewsletterRenderContext $ctx) {
                return $ctx->sendId === null && $ctx->includeTracking === false;
            })
            ->andReturn('');

        $this->pageBuilder->shouldReceive('buildTemplate')->andReturn('');

        $this->resolver->resolve($newsletter, siteId: 1, sendId: null, layoutVersion: $layoutVersion);
        $this->assertTrue(true);
    }

    // =========================================================================
    // unknown / missing content_type fallback
    // =========================================================================

    public function test_unknown_content_type_falls_back_to_manual_path(): void
    {
        $newsletter = $this->makeNewsletter(ContentSourceType::Manual);
        $newsletter->content_type = 'garbage_type';
        $newsletter->legacy_content = 'Some text.';
        $newsletter->content = 'Some text.';

        $this->pageBuilder
            ->shouldReceive('buildNewsletterHtmlFromLayoutSlots')
            ->once()
            ->andReturn('<html>fallback-manual</html>');

        $result = $this->resolver->resolve($newsletter, siteId: 1);

        $this->assertSame('<html>fallback-manual</html>', $result->html);
        $this->assertNull($result->pages);
    }

    // =========================================================================
    // Branding and unsubscribe token are threaded correctly
    // =========================================================================

    public function test_branding_and_unsubscribe_token_passed_to_buildTemplate_for_v2(): void
    {
        $newsletter = $this->makeNewsletter(ContentSourceType::CustomBlocks);
        $newsletter->content_blocks = [['type' => 'text', 'data' => []]];
        $layoutVersion = $this->makeLayoutVersion(schemaVersion: 2);
        $branding = Mockery::mock(NewsletterBrandingConfiguration::class)->makePartial();

        $this->renderPipeline->shouldReceive('renderBody')->andReturn('<body/>');

        $this->pageBuilder
            ->shouldReceive('buildTemplate')
            ->once()
            ->withArgs(function (Newsletter $nl, string $body, int $siteId, ?NewsletterBrandingConfiguration $b, ?string $token) use ($branding) {
                return $b === $branding && $token === 'unsub-token';
            })
            ->andReturn('<html/>');

        $this->resolver->resolve(
            $newsletter,
            siteId: 1,
            unsubscribeToken: 'unsub-token',
            branding: $branding,
            layoutVersion: $layoutVersion,
        );
        $this->assertTrue(true);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Make a Newsletter mock.
     *
     * getBlocks() is explicitly stubbed because makePartial() would call the
     * real implementation, which reads from the database or returns null
     * depending on how the model is wired — neither is safe in a unit test.
     *
     * Tests that need specific block content override getBlocks() themselves.
     */
    private function makeNewsletter(ContentSourceType $type): Newsletter|MockInterface
    {
        $newsletter = new Newsletter();
        $newsletter->id = 99;
        $newsletter->content_type = $type->value;
        $newsletter->legacy_content = null;
        $newsletter->content = null;

        // Default: no blocks. Tests that test custom_blocks content override this.
        // $newsletter->shouldReceive('getBlocks')->andReturn([])->byDefault();

        return $newsletter;
    }

    private function makeLayoutVersion(int $schemaVersion): NewsletterLayoutVersion|MockInterface
    {
        $version = new NewsletterLayoutVersion();
        $version->layout_definition_json = [
            'schema_version' => $schemaVersion,
            'regions' => [
                ['id' => 'top', 'name' => 'Top', 'order' => 1, 'slots' => []],
                ['id' => 'center', 'name' => 'Content', 'order' => 2, 'slots' => []],
                ['id' => 'bottom', 'name' => 'Footer', 'order' => 3, 'slots' => []],
            ],
        ];
        return $version;
    }
}
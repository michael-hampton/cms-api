<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\DTO\Newsletters\NewsletterResolveResult;
use App\Models\Newsletter;
use App\Repositories\Newsletters\NewsletterBrandingRepository;
use App\Repositories\Newsletters\NewsletterLayoutRepository;
use App\Services\Newsletter\NewsletterContentBuilder;
use App\Services\Newsletter\NewsletterContentResolver;
use App\Services\Newsletter\NewsletterPageBuilderService;
use Mockery;
use PHPUnit\Framework\TestCase;

class NewsletterContentBuilderTest extends TestCase
{
    private $pageBuilderService;
    private $brandingRepository;
    private $layoutRepository;
    private $contentResolver;

    private NewsletterContentBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pageBuilderService = Mockery::mock(NewsletterPageBuilderService::class);
        $this->brandingRepository = Mockery::mock(NewsletterBrandingRepository::class);
        $this->layoutRepository = Mockery::mock(NewsletterLayoutRepository::class);
        $this->contentResolver = Mockery::mock(NewsletterContentResolver::class);

        // Default safe stubs
        $this->brandingRepository
            ->shouldReceive('findByNewsletterId')
            ->zeroOrMoreTimes()
            ->andReturn(null)
            ->byDefault();

        $this->layoutRepository
            ->shouldReceive('versionHistory')
            ->zeroOrMoreTimes()
            ->andReturn(collect())
            ->byDefault();

        $this->builder = new NewsletterContentBuilder(
            $this->pageBuilderService,
            $this->brandingRepository,
            $this->layoutRepository,
            $this->contentResolver,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeNewsletter(): Newsletter
    {
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->id = 1;
        $newsletter->layout_id = null;
        $newsletter->content = '[]';
        return $newsletter;
    }

    private function stubResolverWithPages(string $html, array $pages): void
    {
        $this->contentResolver
            ->shouldReceive('resolve')
            ->zeroOrMoreTimes()
            ->andReturn(NewsletterResolveResult::withPages($html, $pages));
    }

    private function stubResolverWithoutPages(string $html): void
    {
        $this->contentResolver
            ->shouldReceive('resolve')
            ->zeroOrMoreTimes()
            ->andReturn(NewsletterResolveResult::withoutPages($html));
    }

    // -------------------------------------------------------------------------
    // AutoPages (resolver returns pages)
    // -------------------------------------------------------------------------

    public function testBuildAutomatedNewsletterSuccessfully(): void
    {
        $newsletter = $this->makeNewsletter();

        $this->stubResolverWithPages('<p>Automated content</p>{{UNSUBSCRIBE_LINK}}', [
            ['id' => 1, 'title' => 'Page 1', 'subtitle' => 'Subtitle 1', 'slug' => 'page-1'],
            ['id' => 2, 'title' => 'Page 2', 'subtitle' => 'Subtitle 2', 'slug' => 'page-2'],
        ]);

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Automated content', $result['html']);
        $this->assertCount(2, $result['pages']);
    }

    public function testBuildAutomatedNewsletterWithNoPagesReturnsErrorInPages(): void
    {
        $newsletter = $this->makeNewsletter();

        // Resolver fetched pages internally but found none — returns withPages + empty array.
        $this->stubResolverWithPages('<p>Content</p>', []);

        $result = $this->builder->build($newsletter, 1, false);

        // Build itself succeeds — the pages sub-key carries the error signal.
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('error', $result['pages']);
        $this->assertEquals('No pages match newsletter criteria', $result['pages']['error']);
    }

    public function testPageBuilderIsNeverCalledForPagesWhenResolverSuppliesThem(): void
    {
        $newsletter = $this->makeNewsletter();

        // The builder must NOT call getPagesForNewsletter — pages come from the DTO.
        $this->pageBuilderService->shouldNotReceive('getPagesForNewsletter');

        $this->stubResolverWithPages('<p>html</p>', [
            ['id' => 1, 'title' => 'T', 'subtitle' => '', 'slug' => 's'],
        ]);

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertTrue($result['success']);
    }

    // -------------------------------------------------------------------------
    // Non-AutoPages (resolver returns null pages)
    // -------------------------------------------------------------------------

    public function testBuildCustomBlocksNewsletterReturnsEmptyPagesArray(): void
    {
        $newsletter = $this->makeNewsletter();

        // Custom blocks / manual — resolver returns withoutPages.
        $this->stubResolverWithoutPages('<p>Custom blocks content</p>{{UNSUBSCRIBE_LINK}}');

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertTrue($result['success']);
        $this->assertSame([], $result['pages']);
    }

    // -------------------------------------------------------------------------
    // Unsubscribe placeholder
    // -------------------------------------------------------------------------

    public function testBuildAddsUnsubscribePlaceholderIfMissing(): void
    {
        $newsletter = $this->makeNewsletter();

        $this->stubResolverWithoutPages('<p>Content without placeholder</p>');

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertStringContainsString('{{UNSUBSCRIBE_LINK}}', $result['html']);
    }

    public function testBuildPreservesExistingUnsubscribePlaceholder(): void
    {
        $newsletter = $this->makeNewsletter();

        $this->stubResolverWithPages('Content{{UNSUBSCRIBE_LINK}}More', [
            ['id' => 1, 'title' => 'Page', 'subtitle' => '', 'slug' => 'page'],
        ]);

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertEquals(1, substr_count($result['html'], '{{UNSUBSCRIBE_LINK}}'));
    }

    // -------------------------------------------------------------------------
    // DomainException rollback contract
    // -------------------------------------------------------------------------

    public function testBuildReturnsDomainExceptionErrorShapeOnResolverThrow(): void
    {
        $newsletter = $this->makeNewsletter();

        $this->contentResolver
            ->shouldReceive('resolve')
            ->once()
            ->andThrow(new \DomainException('No pages match newsletter criteria'));

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertFalse($result['success']);
        $this->assertSame(1, $result['newsletter_id']);
        $this->assertSame('No pages match newsletter criteria', $result['error']);
    }

    // -------------------------------------------------------------------------
    // HTML content pass-through
    // -------------------------------------------------------------------------

    public function testBuildReturnsHtmlFromResolver(): void
    {
        $newsletter = $this->makeNewsletter();

        $this->stubResolverWithoutPages('<p>Hello world</p>{{UNSUBSCRIBE_LINK}}');

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('<p>Hello world</p>', $result['html']);
    }

    public function testBuildWithPreviewModePassesParameterToResolver(): void
    {
        $newsletter = $this->makeNewsletter();

        $this->contentResolver
            ->shouldReceive('resolve')
            ->once()
            ->withArgs(function (Newsletter $nl, int $siteId, $member, $token, bool $isPreview) {
                return $isPreview === true;
            })
            ->andReturn(NewsletterResolveResult::withoutPages('<p>Preview</p>{{UNSUBSCRIBE_LINK}}'));

        $result = $this->builder->build($newsletter, 1, true);

        $this->assertTrue($result['success']);
    }

    public function testBuildPassesForceV2ToResolver(): void
    {
        $newsletter = $this->makeNewsletter();

        $this->contentResolver
            ->shouldReceive('resolve')
            ->once()
            ->withArgs(function (Newsletter $nl, int $siteId, $member, $token, bool $isPreview, $sendId, $branding, $layoutVersion, bool $forceV2) {
                return $forceV2 === true;
            })
            ->andReturn(NewsletterResolveResult::withoutPages('<p>V2</p>{{UNSUBSCRIBE_LINK}}'));

        $result = $this->builder->build($newsletter, 1, false, null, true);

        $this->assertTrue($result['success']);
    }

    // -------------------------------------------------------------------------
    // Branding / layout repository wiring
    // -------------------------------------------------------------------------

    public function testBuildFetchesBrandingByNewsletterId(): void
    {
        $newsletter = $this->makeNewsletter();

        $this->brandingRepository
            ->shouldReceive('findByNewsletterId')
            ->once()
            ->with(1)
            ->andReturn(null);

        $this->stubResolverWithoutPages('<p>x</p>{{UNSUBSCRIBE_LINK}}');

        $this->builder->build($newsletter, 1, false);

        // Mockery assertion: once() above enforces the call happened.
        $this->assertTrue(true);
    }

    public function testBuildFetchesVersionHistoryWhenLayoutIdPresent(): void
    {
        $newsletter = $this->makeNewsletter();
        $newsletter->layout_id = 99;

        $this->layoutRepository
            ->shouldReceive('versionHistory')
            ->once()
            ->with(99)
            ->andReturn(collect());

        $this->stubResolverWithoutPages('<p>x</p>{{UNSUBSCRIBE_LINK}}');

        $this->builder->build($newsletter, 1, false);

        $this->assertTrue(true);
    }

    public function testBuildSkipsVersionHistoryWhenNoLayoutId(): void
    {
        $newsletter = $this->makeNewsletter();
        $newsletter->layout_id = null;

        $this->layoutRepository->shouldNotReceive('versionHistory');

        $this->stubResolverWithoutPages('<p>x</p>{{UNSUBSCRIBE_LINK}}');

        $this->builder->build($newsletter, 1, false);

        $this->assertTrue(true);
    }
}
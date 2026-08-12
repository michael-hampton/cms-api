<?php

namespace App\Tests\Unit\Services\Front;

use App\Models\Block;
use App\Models\Page;
use App\Parsers\PageGridRenderer;
use App\Parsers\ZoneBlockParser;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\Pages\PageGridRepository;
use App\Services\Adverts\PageVisibilityResolver;
use App\Services\Cms\Pages\BlockParserService;
use App\Services\Cms\Pages\PageRenderService;
use App\Services\PublicContent\Config\PublicContentConfigSource;
use App\Tests\Unit\UnitTestCase;
use Mockery;
use Mockery\MockInterface;

class PageRenderServiceTest extends UnitTestCase
{
    private int $siteId = 1;

    private BlockRepository|MockInterface $blockRepository;
    private BlockParserService|MockInterface $blockParserService;
    private ZoneBlockParser|MockInterface $zoneParser;
    private PageGridRepository|MockInterface $pageGridRepository;
    private PageVisibilityResolver|MockInterface $pageVisibilityResolver;
    private PageGridRenderer|MockInterface $pageGridRenderer;
    private PublicContentConfigSource|MockInterface $publicContentConfig;
    private PageRenderService $pageRenderService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->blockRepository = Mockery::mock(BlockRepository::class);
        $this->blockParserService = Mockery::mock(BlockParserService::class);
        $this->zoneParser = Mockery::mock(ZoneBlockParser::class);
        $this->pageGridRepository = Mockery::mock(PageGridRepository::class);
        $this->pageVisibilityResolver = Mockery::mock(PageVisibilityResolver::class);
        $this->pageGridRenderer = Mockery::mock(PageGridRenderer::class);
        $this->publicContentConfig = Mockery::mock(PublicContentConfigSource::class);

        $this->publicContentConfig
            ->shouldReceive('get')
            ->andReturnUsing(function (int $siteId, string $key, mixed $default = null) {
                return match ($key) {
                    'widgets.adverts.page_types' => ['*'],
                    'widgets.adverts.frequency' => 'balanced',
                    default => $default,
                };
            });

        $this->pageRenderService = new PageRenderService(
            $this->blockRepository,
            $this->blockParserService,
            $this->zoneParser,
            $this->pageGridRepository,
            $this->pageVisibilityResolver,
            $this->pageGridRenderer,
            $this->publicContentConfig,
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function createMockBlock(int $id, string $context = 'default', int $order = 1): Block
    {
        $block = new Block();
        $block->id = $id;
        $block->page_id = 1;
        $block->type = 'text';
        $block->order = $order;
        $block->data = ['context' => $context, 'content' => "Block $id content"];
        return $block;
    }

    private function defaultZonesResult(array $usedIds = []): array
    {
        return ['html' => '', 'usedBlockIds' => $usedIds];
    }

    private function setupNoAdverts(Page $page): void
    {
        $this->pageVisibilityResolver
            ->shouldReceive('getAdvertBlocksForPage')
            ->with($page, $this->siteId, null)
            ->andReturn([]);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_it_renders_page_with_zones_and_excludes_used_blocks(): void
    {
        $page = new Page();
        $page->id = 1;

        $block1 = $this->createMockBlock(1, 'default');
        $block2 = $this->createMockBlock(2, 'sidebar');
        $block3 = $this->createMockBlock(3, 'default');
        $block4 = $this->createMockBlock(4, 'sidebar');

        $zonesResult = [
            'html' => '<div class="zone-content">Zone content with blocks 1 and 2</div>',
            'usedBlockIds' => [1, 2],
        ];

        $this->pageGridRepository->shouldReceive('getActiveGridForPage')->with($page->id)->andReturn(collect());
        $this->zoneParser->shouldReceive('buildZonesHtml')->with($page)->once()->andReturn($zonesResult);
        $this->blockRepository->shouldReceive('getPageBlocks')->with($page->id)->once()->andReturn(collect([$block1, $block2, $block3, $block4]));

        $this->setupNoAdverts($page);

        $this->blockParserService->shouldReceive('buildBlock')
            ->with($block3->page_id, Mockery::any(), $block3->order, false, $this->siteId)
            ->once()->andReturn('<div>Block 3 content</div>');

        $this->blockParserService->shouldReceive('buildBlock')
            ->with($block4->page_id, Mockery::any(), $block4->order, false, $this->siteId)
            ->once()->andReturn('<div>Block 4 content</div>');

        $html = $this->pageRenderService->renderPage($page, $this->siteId);

        $this->assertStringContainsString('Zone content with blocks 1 and 2', $html['main']);
        $this->assertStringContainsString('Block 3 content', $html['main']);
        $this->assertStringContainsString('Block 4 content', $html['sidebar']);
    }

    public function test_it_separates_sidebar_and_main_content_blocks(): void
    {
        $page = new Page();
        $page->id = 1;

        $mainBlock1 = $this->createMockBlock(1, 'default');
        $sidebarBlock1 = $this->createMockBlock(2, 'sidebar');
        $mainBlock2 = $this->createMockBlock(3, 'default');
        $sidebarBlock2 = $this->createMockBlock(4, 'sidebar');

        $this->pageGridRepository->shouldReceive('getActiveGridForPage')->andReturn(collect());
        $this->zoneParser->shouldReceive('buildZonesHtml')->andReturn($this->defaultZonesResult());
        $this->blockRepository->shouldReceive('getPageBlocks')->andReturn(collect([$mainBlock1, $sidebarBlock1, $mainBlock2, $sidebarBlock2]));
        $this->setupNoAdverts($page);

        $this->blockParserService->shouldReceive('buildBlock')->with(1, Mockery::any(), $mainBlock1->order, false, $this->siteId)->once()->andReturn('<div>Main Block 1</div>');
        $this->blockParserService->shouldReceive('buildBlock')->with(1, Mockery::any(), $sidebarBlock1->order, false, $this->siteId)->once()->andReturn('<div>Sidebar Block 1</div>');
        $this->blockParserService->shouldReceive('buildBlock')->with(1, Mockery::any(), $mainBlock2->order, false, $this->siteId)->once()->andReturn('<div>Main Block 2</div>');
        $this->blockParserService->shouldReceive('buildBlock')->with(1, Mockery::any(), $sidebarBlock2->order, false, $this->siteId)->once()->andReturn('<div>Sidebar Block 2</div>');

        $result = $this->pageRenderService->renderPage($page, $this->siteId);

        $this->assertStringContainsString('Main Block 1', $result['main']);
        $this->assertStringContainsString('Main Block 2', $result['main']);
        $this->assertStringContainsString('Sidebar Block 1', $result['sidebar']);
        $this->assertStringContainsString('Sidebar Block 2', $result['sidebar']);
    }

    public function test_it_continues_rendering_after_block_error(): void
    {
        $page = new Page();
        $page->id = 1;

        $block1 = $this->createMockBlock(1, 'default');
        $block2 = $this->createMockBlock(2, 'default');
        $block3 = $this->createMockBlock(3, 'default');

        $this->pageGridRepository->shouldReceive('getActiveGridForPage')->andReturn(collect());
        $this->zoneParser->shouldReceive('buildZonesHtml')->andReturn($this->defaultZonesResult());
        $this->blockRepository->shouldReceive('getPageBlocks')->andReturn(collect([$block1, $block2, $block3]));
        $this->setupNoAdverts($page);

        $this->blockParserService->shouldReceive('buildBlock')->with(1, Mockery::any(), $block1->order, false, $this->siteId)->once()->andReturn('<div>Block 1</div>');
        $this->blockParserService->shouldReceive('buildBlock')->with(1, Mockery::any(), $block2->order, false, $this->siteId)->once()->andThrow(new \Exception('Block 2 error'));
        $this->blockParserService->shouldReceive('buildBlock')->with(1, Mockery::any(), $block3->order, false, $this->siteId)->once()->andReturn('<div>Block 3</div>');

        $result = $this->pageRenderService->renderPage($page, $this->siteId);

        $this->assertStringContainsString('Block 1', $result['main']);
        $this->assertStringContainsString('Block 3', $result['main']);
        $this->assertStringNotContainsString('Block 2', $result['main']);
    }

    public function test_it_preserves_block_order(): void
    {
        $page = new Page();
        $page->id = 1;

        $block1 = $this->createMockBlock(1, 'default', 1);
        $block2 = $this->createMockBlock(2, 'default', 2);
        $block3 = $this->createMockBlock(3, 'default', 3);

        $this->pageGridRepository->shouldReceive('getActiveGridForPage')->andReturn(collect());
        $this->zoneParser->shouldReceive('buildZonesHtml')->andReturn($this->defaultZonesResult());
        $this->blockRepository->shouldReceive('getPageBlocks')->andReturn(collect([$block1, $block2, $block3]));
        $this->setupNoAdverts($page);

        $this->blockParserService->shouldReceive('buildBlock')->with(1, Mockery::any(), 1, false, $this->siteId)->once()->andReturn('<!-- Block 1 -->');
        $this->blockParserService->shouldReceive('buildBlock')->with(1, Mockery::any(), 2, false, $this->siteId)->once()->andReturn('<!-- Block 2 -->');
        $this->blockParserService->shouldReceive('buildBlock')->with(1, Mockery::any(), 3, false, $this->siteId)->once()->andReturn('<!-- Block 3 -->');

        $result = $this->pageRenderService->renderPage($page, $this->siteId);

        $pos1 = strpos($result['main'], 'Block 1');
        $pos2 = strpos($result['main'], 'Block 2');
        $pos3 = strpos($result['main'], 'Block 3');

        $this->assertLessThan($pos2, $pos1);
        $this->assertLessThan($pos3, $pos2);
    }

    public function test_adverts_are_injected_between_content_blocks(): void
    {
        $page = new Page();
        $page->id = 1;

        $block1 = $this->createMockBlock(1, 'default', 1);
        $block2 = $this->createMockBlock(2, 'default', 2);
        $block3 = $this->createMockBlock(3, 'default', 3);
        $block4 = $this->createMockBlock(4, 'default', 4);

        $this->pageGridRepository->shouldReceive('getActiveGridForPage')->andReturn(collect());
        $this->zoneParser->shouldReceive('buildZonesHtml')->andReturn($this->defaultZonesResult());
        $this->blockRepository->shouldReceive('getPageBlocks')->andReturn(collect([$block1, $block2, $block3, $block4]));

        $this->pageVisibilityResolver
            ->shouldReceive('getAdvertBlocksForPage')
            ->with($page, $this->siteId, null)
            ->andReturn([
                '<div data-advert="offer" class="advert-block offer-block">Offer 1</div>',
                '<div data-advert="deal" class="advert-block deal-block">Deal 1</div>',
            ]);

        $this->blockParserService->shouldReceive('buildBlock')->andReturn('<div>Content</div>');

        $result = $this->pageRenderService->renderPage($page, $this->siteId);

        $this->assertStringContainsString('Offer 1', $result['main']);
        $this->assertStringContainsString('Deal 1', $result['main']);
        $this->assertStringContainsString('data-advert="offer"', $result['main']);
        $this->assertStringContainsString('data-advert="deal"', $result['main']);

        $firstContentPos = strpos($result['main'], 'Content');
        $firstAdvertPos = strpos($result['main'], 'data-advert');
        $this->assertGreaterThan($firstContentPos, $firstAdvertPos);
    }

    public function test_adverts_not_injected_into_sidebar(): void
    {
        $page = new Page();
        $page->id = 1;

        $sidebarBlock = $this->createMockBlock(1, 'sidebar', 1);
        $mainBlock = $this->createMockBlock(2, 'default', 2);

        $this->pageGridRepository->shouldReceive('getActiveGridForPage')->andReturn(collect());
        $this->zoneParser->shouldReceive('buildZonesHtml')->andReturn($this->defaultZonesResult());
        $this->blockRepository->shouldReceive('getPageBlocks')->andReturn(collect([$sidebarBlock, $mainBlock]));

        $this->pageVisibilityResolver
            ->shouldReceive('getAdvertBlocksForPage')
            ->andReturn(['<div data-advert="offer" class="advert-block">Advert</div>']);

        $this->blockParserService->shouldReceive('buildBlock')->andReturn('<div>Content</div>');

        $result = $this->pageRenderService->renderPage($page, $this->siteId);

        $this->assertStringNotContainsString('data-advert', $result['sidebar']);
        $this->assertStringContainsString('data-advert', $result['main']);
    }

    public function test_remaining_adverts_appended_after_all_content_blocks(): void
    {
        $page = new Page();
        $page->id = 1;

        $block1 = $this->createMockBlock(1, 'default', 1);

        $this->pageGridRepository->shouldReceive('getActiveGridForPage')->andReturn(collect());
        $this->zoneParser->shouldReceive('buildZonesHtml')->andReturn($this->defaultZonesResult());
        $this->blockRepository->shouldReceive('getPageBlocks')->andReturn(collect([$block1]));

        $this->pageVisibilityResolver
            ->shouldReceive('getAdvertBlocksForPage')
            ->andReturn([
                '<div data-advert="offer" class="advert-block">Advert 1</div>',
                '<div data-advert="deal" class="advert-block">Advert 2</div>',
                '<div data-advert="reward" class="advert-block">Advert 3</div>',
            ]);

        $this->blockParserService->shouldReceive('buildBlock')->andReturn('<div>Content</div>');

        $result = $this->pageRenderService->renderPage($page, $this->siteId);

        $this->assertStringContainsString('data-advert="offer"', $result['main']);
        $this->assertStringContainsString('data-advert="deal"', $result['main']);
        $this->assertStringContainsString('data-advert="reward"', $result['main']);
    }

    public function test_advert_frequency_scales_with_content_block_count(): void
    {
        $page = new Page();
        $page->id = 1;

        // 9 content blocks — balanced frequency fits multiple inline/overflow adverts
        $blocks = array_map(fn($i) => $this->createMockBlock($i, 'default', $i), range(1, 9));

        $this->pageGridRepository->shouldReceive('getActiveGridForPage')->andReturn(collect());
        $this->zoneParser->shouldReceive('buildZonesHtml')->andReturn($this->defaultZonesResult());
        $this->blockRepository->shouldReceive('getPageBlocks')->andReturn(collect($blocks));

        $this->pageVisibilityResolver
            ->shouldReceive('getAdvertBlocksForPage')
            ->andReturn([
                '<div data-advert="offer">Advert 1</div>',
                '<div data-advert="deal">Advert 2</div>',
                '<div data-advert="reward">Advert 3</div>',
            ]);

        $this->blockParserService->shouldReceive('buildBlock')->andReturn('<div>Content</div>');

        $result = $this->pageRenderService->renderPage($page, $this->siteId);

        $this->assertStringContainsString('Advert 1', $result['main']);
        $this->assertStringContainsString('Advert 2', $result['main']);
        $this->assertStringContainsString('Advert 3', $result['main']);
    }

    public function test_remaining_adverts_appended_one_at_a_time_after_content(): void
    {
        $page = new Page();
        $page->id = 1;

        // 4 content blocks, 3 adverts — 1 fits inline, 2 appended after
        $blocks = array_map(fn($i) => $this->createMockBlock($i, 'default', $i), range(1, 4));

        $this->pageGridRepository->shouldReceive('getActiveGridForPage')->andReturn(collect());
        $this->zoneParser->shouldReceive('buildZonesHtml')->andReturn($this->defaultZonesResult());
        $this->blockRepository->shouldReceive('getPageBlocks')->andReturn(collect($blocks));

        $this->pageVisibilityResolver
            ->shouldReceive('getAdvertBlocksForPage')
            ->andReturn([
                '<div data-advert="offer">Advert 1</div>',
                '<div data-advert="deal">Advert 2</div>',
                '<div data-advert="reward">Advert 3</div>',
            ]);

        $this->blockParserService->shouldReceive('buildBlock')->andReturn('<div>Content</div>');

        $result = $this->pageRenderService->renderPage($page, $this->siteId);

        // All 3 adverts should appear
        $this->assertStringContainsString('Advert 1', $result['main']);
        $this->assertStringContainsString('Advert 2', $result['main']);
        $this->assertStringContainsString('Advert 3', $result['main']);

        // Advert 2 and 3 must appear after all content (appended, not interleaved)
        $lastContentPos = strrpos($result['main'], 'Content');
        $advert2Pos = strpos($result['main'], 'Advert 2');
        $advert3Pos = strpos($result['main'], 'Advert 3');

        $this->assertGreaterThan($lastContentPos, $advert2Pos);
        $this->assertGreaterThan($advert2Pos, $advert3Pos);
    }

    public function test_no_adverts_injected_when_insufficient_content_blocks(): void
    {
        $page = new Page();
        $page->id = 1;

        // Only 2 content blocks — minGap is 2 so no inline injection, but appended after
        $blocks = array_map(fn($i) => $this->createMockBlock($i, 'default', $i), range(1, 2));

        $this->pageGridRepository->shouldReceive('getActiveGridForPage')->andReturn(collect());
        $this->zoneParser->shouldReceive('buildZonesHtml')->andReturn($this->defaultZonesResult());
        $this->blockRepository->shouldReceive('getPageBlocks')->andReturn(collect($blocks));

        $this->pageVisibilityResolver
            ->shouldReceive('getAdvertBlocksForPage')
            ->andReturn(['<div data-advert="offer">Advert 1</div>']);

        $this->blockParserService->shouldReceive('buildBlock')->andReturn('<div>Content</div>');

        $result = $this->pageRenderService->renderPage($page, $this->siteId);

        // Advert still appears — appended after content, just not interleaved
        $this->assertStringContainsString('Advert 1', $result['main']);

        $lastContentPos = strrpos($result['main'], 'Content');
        $advertPos = strpos($result['main'], 'Advert 1');
        $this->assertGreaterThan($lastContentPos, $advertPos);
    }

    public function test_only_one_advert_injected_inline_remainder_in_overflow_row(): void
    {
        $page = new Page();
        $page->id = 1;

        // 4 blocks → floor(4/3) = 1 inline advert max
        $blocks = array_map(fn($i) => $this->createMockBlock($i, 'default', $i), range(1, 4));

        $this->pageGridRepository->shouldReceive('getActiveGridForPage')->andReturn(collect());
        $this->zoneParser->shouldReceive('buildZonesHtml')->andReturn($this->defaultZonesResult());
        $this->blockRepository->shouldReceive('getPageBlocks')->andReturn(collect($blocks));

        $this->pageVisibilityResolver
            ->shouldReceive('getAdvertBlocksForPage')
            ->andReturn([
                '<div data-advert="offer">Advert 1</div>',
                '<div data-advert="deal">Advert 2</div>',
                '<div data-advert="boost">Advert 3</div>',
            ]);

        $this->blockParserService->shouldReceive('buildBlock')->andReturn('<div>Content</div>');

        $result = $this->pageRenderService->renderPage($page, $this->siteId);

        $this->assertStringContainsString('Advert 1', $result['main']);
        $this->assertStringContainsString('Advert 2', $result['main']);
        $this->assertStringContainsString('Advert 3', $result['main']);

        $this->assertStringContainsString('advert-overflow-row', $result['main']);

        $overflowPos = strpos($result['main'], 'advert-overflow-row');
        $advert2Pos = strpos($result['main'], 'Advert 2');
        $advert3Pos = strpos($result['main'], 'Advert 3');

        $this->assertGreaterThan($overflowPos, $advert2Pos);
        $this->assertGreaterThan($overflowPos, $advert3Pos);

        $advert1Pos = strpos($result['main'], 'Advert 1');
        $this->assertLessThan($overflowPos, $advert1Pos);
    }
}

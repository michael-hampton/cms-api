<?php

namespace App\Tests\Unit\Services;

use App\Models\Block;
use App\Models\Page;
use App\Parsers\ZoneBlockParser;
use App\Repositories\BlockRepository;
use App\Services\BlockParserService;
use App\Services\PageRenderService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class PageRenderServiceTest extends FunctionalTestCase
{
    private $blockRepository;
    private $blockParserService;
    private $zoneParser;
    private $pageRenderService;
    private $container;

    public function test_it_renders_page_with_zones_and_excludes_used_blocks()
    {
        // Arrange
        $page = new Page();
        $page->id = 1;

        // Create blocks - some will be in zones, some won't
        $block1 = $this->createMockBlock(1, 'default');
        $block2 = $this->createMockBlock(2, 'sidebar');
        $block3 = $this->createMockBlock(3, 'default');
        $block4 = $this->createMockBlock(4, 'sidebar');

        $allBlocks = [$block1, $block2, $block3, $block4];

        // Blocks 1 and 2 will be rendered in zones
        $zonesResult = [
            'html' => '<div class="zone-content">Zone content with blocks 1 and 2</div>',
            'usedBlockIds' => [1, 2]
        ];

        // Mock zone parser
        $this->zoneParser->shouldReceive('buildZonesHtml')
            ->with($page)
            ->once()
            ->andReturn($zonesResult);

        // Mock block repository
        $this->blockRepository->shouldReceive('getPageBlocks')
            ->with($page->id)
            ->once()
            ->andReturn(collect($allBlocks));

        // Mock block parser service - only blocks 3 and 4 should be rendered
        $this->blockParserService->shouldReceive('buildBlock')
            ->with($block3->page_id, Mockery::any(), $block3->order, false, $this->siteId)
            ->once()
            ->andReturn('<div>Block 3 content</div>');

        $this->blockParserService->shouldReceive('buildBlock')
            ->with($block4->page_id, Mockery::any(), $block4->order, false, $this->siteId)
            ->once()
            ->andReturn('<div>Block 4 content</div>');

        // Act
        $html = $this->pageRenderService->renderPage($page, $this->siteId);

        // Assert
        $this->assertStringContainsString('Zone content with blocks 1 and 2', $html['main']);
        $this->assertStringContainsString('Block 3 content', $html['main']);
        $this->assertStringContainsString('Block 4 content', $html['sidebar']);
    }

    private function createMockBlock($id, $context = 'default', $order = 1)
    {
        $block = new Block();
        $block->id = $id;
        $block->page_id = 1;
        $block->type = 'text';
        $block->order = $order;
        $block->data = [
            'context' => $context,
            'content' => "Block $id content"
        ];

        return $block;
    }

    public function test_it_separates_sidebar_and_main_content_blocks()
    {
        // Arrange
        $page = new Page();
        $page->id = 1;

        $mainBlock1 = $this->createMockBlock(1, 'default');
        $sidebarBlock1 = $this->createMockBlock(2, 'sidebar');
        $mainBlock2 = $this->createMockBlock(3, 'default');
        $sidebarBlock2 = $this->createMockBlock(4, 'sidebar');

        $allBlocks = [$mainBlock1, $sidebarBlock1, $mainBlock2, $sidebarBlock2];

        $zonesResult = [
            'html' => '',
            'usedBlockIds' => []
        ];

        $this->zoneParser->shouldReceive('buildZonesHtml')
            ->andReturn($zonesResult);

        $this->blockRepository->shouldReceive('getPageBlocks')
            ->andReturn(collect($allBlocks));

        // Expect main content blocks to be rendered
        $this->blockParserService->shouldReceive('buildBlock')
            ->with($mainBlock1->page_id, Mockery::any(), $mainBlock1->order, false, $this->siteId)
            ->once()
            ->andReturn('<div class="main-block">Main Block 1</div>');

        $this->blockParserService->shouldReceive('buildBlock')
            ->with($mainBlock2->page_id, Mockery::any(), $mainBlock2->order, false, $this->siteId)
            ->once()
            ->andReturn('<div class="main-block">Sidebar Block 1</div>');

        // Expect sidebar blocks to be rendered
        $this->blockParserService->shouldReceive('buildBlock')
            ->with($sidebarBlock1->page_id, Mockery::any(), $sidebarBlock1->order, false, $this->siteId)
            ->once()
            ->andReturn('<div class="sidebar-block">Main Block 2</div>');

        $this->blockParserService->shouldReceive('buildBlock')
            ->with($sidebarBlock2->page_id, Mockery::any(), $sidebarBlock2->order, false, $this->siteId)
            ->once()
            ->andReturn('<div class="sidebar-block">Sidebar Block 2</div>');

        // Act
        $result = $this->pageRenderService->renderPage($page, $this->siteId);

        // Assert - check structure includes both main and sidebar content
        $this->assertStringContainsString('Main Block 1', $result['main']);
        $this->assertStringContainsString('Main Block 2', $result['main']);
        $this->assertStringContainsString('Sidebar Block 1', $result['sidebar']);
        $this->assertStringContainsString('Sidebar Block 2', $result['sidebar']);
    }

    public function test_it_continues_rendering_after_block_error()
    {
        // Arrange
        $page = new Page();
        $page->id = 1;

        $block1 = $this->createMockBlock(1, 'default');
        $block2 = $this->createMockBlock(2, 'default');
        $block3 = $this->createMockBlock(3, 'default');

        $zonesResult = ['html' => '', 'usedBlockIds' => []];

        $this->zoneParser->shouldReceive('buildZonesHtml')
            ->andReturn($zonesResult);

        $this->blockRepository->shouldReceive('getPageBlocks')
            ->andReturn(collect([$block1, $block2, $block3]));

        // Block 1 succeeds
        $this->blockParserService->shouldReceive('buildBlock')
            ->with($block1->page_id, Mockery::any(), $block1->order, false, $this->siteId)
            ->once()
            ->andReturn('<div>Block 1</div>');

        // Block 2 throws exception
        $this->blockParserService->shouldReceive('buildBlock')
            ->with($block2->page_id, Mockery::any(), $block2->order, false, $this->siteId)
            ->once()
            ->andThrow(new \Exception('Block 2 error'));

        // Block 3 succeeds
        $this->blockParserService->shouldReceive('buildBlock')
            ->with($block3->page_id, Mockery::any(), $block3->order, false, $this->siteId)
            ->once()
            ->andReturn('<div>Block 3</div>');

        // Act
        $html = $this->pageRenderService->renderPage($page, $this->siteId);

        // Assert
        $this->assertStringContainsString('Block 1', $html['main']);
        $this->assertStringContainsString('Block 3', $html['main']);
        $this->assertStringNotContainsString('Block 2', $html['main']);
    }

    public function test_it_preserves_block_order()
    {
        // Arrange
        $page = new Page();
        $page->id = 1;

        $block1 = $this->createMockBlock(1, 'default', 1);
        $block2 = $this->createMockBlock(2, 'default', 2);
        $block3 = $this->createMockBlock(3, 'default', 3);

        $zonesResult = ['html' => '', 'usedBlockIds' => []];

        $this->zoneParser->shouldReceive('buildZonesHtml')
            ->andReturn($zonesResult);

        $this->blockRepository->shouldReceive('getPageBlocks')
            ->andReturn(collect([$block1, $block2, $block3]));

        $this->blockParserService->shouldReceive('buildBlock')
            ->with($block1->page_id, Mockery::any(), 1, false, $this->siteId)
            ->once()
            ->andReturn('<!-- Block 1 -->');

        $this->blockParserService->shouldReceive('buildBlock')
            ->with($block2->page_id, Mockery::any(), 2, false, $this->siteId)
            ->once()
            ->andReturn('<!-- Block 2 -->');

        $this->blockParserService->shouldReceive('buildBlock')
            ->with($block3->page_id, Mockery::any(), 3, false, $this->siteId)
            ->once()
            ->andReturn('<!-- Block 3 -->');

        // Act
        $html = $this->pageRenderService->renderPage($page, $this->siteId);

        // Assert - check blocks appear in order
        $pos1 = strpos($html['main'], 'Block 1');
        $pos2 = strpos($html['main'], 'Block 2');
        $pos3 = strpos($html['main'], 'Block 3');

        $this->assertLessThan($pos2, $pos1);
        $this->assertLessThan($pos3, $pos2);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Mock dependencies
        $this->blockRepository = Mockery::mock(BlockRepository::class);
        $this->blockParserService = Mockery::mock(BlockParserService::class);
        $this->zoneParser = Mockery::mock(ZoneBlockParser::class);

        // Create service instance
        $this->pageRenderService = new PageRenderService(
            $this->blockRepository,
            $this->blockParserService,
            $this->zoneParser,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
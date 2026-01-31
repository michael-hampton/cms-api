<?php

namespace App\Tests\Unit\Parsers;

use App\Models\Block;
use App\Models\Page;
use App\Parsers\ZoneBlockParser;
use App\Repositories\Cms\BlockRepository;
use App\Services\Cms\Pages\BlockParserService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class ZoneBlockParserTest extends FunctionalTestCase
{
    private $blockRepository;
    private $blockParserService;
    private $parser;

    public function testGetType()
    {
        $this->assertEquals('zone', $this->parser->getType());
    }

    public function testParseZoneData()
    {
        $data = [
            'id' => 'zone-a',
            'name' => 'Test Zone',
            'columns' => 2,
            'blocks' => [[1], [2]],
            'options' => [
                'background' => 'muted',
                'padding' => 'large',
                'width' => 'contained'
            ],
            'sortOrder' => 1
        ];

        $result = $this->parser->parse($data);

        $this->assertEquals('zone-a', $result['id']);
        $this->assertEquals('Test Zone', $result['name']);
        $this->assertEquals(2, $result['columns']);
        $this->assertEquals([[1], [2]], $result['blocks']);
        $this->assertEquals('muted', $result['options']['background']);
        $this->assertEquals(1, $result['sortOrder']);
    }

    public function testParseDefaultOptions()
    {
        $data = [
            'id' => 'zone-a',
            'name' => 'Test Zone',
            'columns' => 1,
            'blocks' => [[1]],
            'sortOrder' => 0
        ];

        $result = $this->parser->parse($data);

        $this->assertEquals('default', $result['options']['background']);
        $this->assertEquals('medium', $result['options']['padding']);
        $this->assertEquals('contained', $result['options']['width']);
    }

    public function testBuildZonesHtmlWithNoZones()
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $page->zones = null;

        $result = $this->parser->buildZonesHtml($page);

        $this->assertEquals('', $result['html']);
        $this->assertEquals([], $result['usedBlockIds']);
    }

    public function testBuildZonesHtmlWithSingleColumn()
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $page->zones = json_encode([
            [
                'id' => 'zone-a',
                'name' => 'Test Zone',
                'columns' => 1,
                'blocks' => [[1]],
                'options' => [
                    'background' => 'default',
                    'padding' => 'medium',
                    'width' => 'contained'
                ],
                'sortOrder' => 0
            ]
        ]);

        $block = Mockery::mock(Block::class)->makePartial();
        $block->id = 1;
        $block->page_id = 1;
        $block->type = 'text';
        $block->data = ['paragraphs' => ['Test content']];

        $this->blockRepository->shouldReceive('getPageBlocks')
            ->with(1)
            ->once()
            ->andReturn(collect([$block]));

        $this->blockParserService->shouldReceive('buildBlock')
            ->once()
            ->andReturn('<p>Test content</p>');

        $result = $this->parser->buildZonesHtml($page);

        $this->assertStringContainsString('zone-a', $result['html']);
        $this->assertStringContainsString('zone-columns-1', $result['html']);
        $this->assertStringContainsString('zone-background-default', $result['html']);
        $this->assertEquals([1], $result['usedBlockIds']);
    }

    public function testBuildZonesHtmlWithMultipleColumns()
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $page->zones = json_encode([
            [
                'id' => 'zone-b',
                'name' => 'Two Column Zone',
                'columns' => 2,
                'blocks' => [[1], [2]],
                'options' => [
                    'background' => 'muted',
                    'padding' => 'large',
                    'width' => 'contained'
                ],
                'sortOrder' => 0
            ]
        ]);

        $block1 = Mockery::mock(Block::class)->makePartial();
        $block1->id = 1;
        $block1->page_id = 1;
        $block1->type = 'text';
        $block1->data = ['paragraphs' => ['Column 1']];

        $block2 = Mockery::mock(Block::class)->makePartial();
        $block2->id = 2;
        $block2->page_id = 1;
        $block2->type = 'text';
        $block2->data = ['paragraphs' => ['Column 2']];

        $this->blockRepository->shouldReceive('getPageBlocks')
            ->with(1)
            ->once()
            ->andReturn(collect([$block1, $block2]));

        $this->blockParserService->shouldReceive('buildBlock')
            ->twice()
            ->andReturn('<p>Content</p>');

        $result = $this->parser->buildZonesHtml($page);

        $this->assertStringContainsString('zone-b', $result['html']);
        $this->assertStringContainsString('zone-columns-2', $result['html']);
        $this->assertStringContainsString('zone-background-muted', $result['html']);
        $this->assertStringContainsString('zone-padding-large', $result['html']);
        $this->assertEquals([1, 2], $result['usedBlockIds']);
    }

    public function testBuildZonesHtmlSkipsDuplicateBlocks()
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $page->zones = json_encode([
            [
                'id' => 'zone-a',
                'name' => 'Zone A',
                'columns' => 1,
                'blocks' => [[1]],
                'options' => [],
                'sortOrder' => 0
            ],
            [
                'id' => 'zone-b',
                'name' => 'Zone B',
                'columns' => 1,
                'blocks' => [[1]], // Same block ID
                'options' => [],
                'sortOrder' => 1
            ]
        ]);

        $block = Mockery::mock(Block::class)->makePartial();
        $block->id = 1;
        $block->page_id = 1;
        $block->type = 'text';
        $block->data = ['paragraphs' => ['Test']];

        $this->blockRepository->shouldReceive('getPageBlocks')
            ->with(1)
            ->once()
            ->andReturn(collect([$block]));

        $this->blockParserService->shouldReceive('buildBlock')
            ->once() // Should only be called once
            ->andReturn('<p>Test</p>');

        $result = $this->parser->buildZonesHtml($page);

        $this->assertStringContainsString('zone-a', $result['html']);
        $this->assertStringContainsString('zone-b', $result['html']);
        $this->assertEquals([1], $result['usedBlockIds']); // Should only have block 1 once
    }

    public function testBuildZonesHtmlSortsZonesBySortOrder()
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $page->zones = json_encode([
            [
                'id' => 'zone-b',
                'name' => 'Zone B',
                'columns' => 1,
                'blocks' => [[2]],
                'options' => [],
                'sortOrder' => 2
            ],
            [
                'id' => 'zone-a',
                'name' => 'Zone A',
                'columns' => 1,
                'blocks' => [[1]],
                'options' => [],
                'sortOrder' => 1
            ]
        ]);

        $block1 = Mockery::mock(Block::class)->makePartial();
        $block1->id = 1;
        $block1->page_id = 1;
        $block1->type = 'text';
        $block1->data = ['paragraphs' => ['First']];

        $block2 = Mockery::mock(Block::class)->makePartial();
        $block2->id = 2;
        $block2->page_id = 1;
        $block2->type = 'text';
        $block2->data = ['paragraphs' => ['Second']];

        $this->blockRepository->shouldReceive('getPageBlocks')
            ->with(1)
            ->once()
            ->andReturn(collect([$block1, $block2]));

        $this->blockParserService->shouldReceive('buildBlock')
            ->twice()
            ->andReturn('<p>Content</p>');

        $result = $this->parser->buildZonesHtml($page);

        // Zone A should appear before Zone B in HTML
        $posA = strpos($result['html'], 'zone-a');
        $posB = strpos($result['html'], 'zone-b');

        $this->assertLessThan($posB, $posA);
    }

    public function testBuildZonesHtmlHandlesEmptyBlocks()
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $page->zones = json_encode([
            [
                'id' => 'zone-a',
                'name' => 'Empty Zone',
                'columns' => 2,
                'blocks' => [[], []],
                'options' => [],
                'sortOrder' => 0
            ]
        ]);

        $this->blockRepository->shouldReceive('getPageBlocks')
            ->with(1)
            ->once()
            ->andReturn(collect([]));

        $result = $this->parser->buildZonesHtml($page);

        $this->assertStringContainsString('zone-a', $result['html']);
        $this->assertStringContainsString('zone-columns-2', $result['html']);
        $this->assertEquals([], $result['usedBlockIds']);
    }

    public function testBuildZonesHtmlHandlesNullBlocks()
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $page->zones = json_encode([
            [
                'id' => 'zone-a',
                'name' => 'Zone with Nulls',
                'columns' => 3,
                'blocks' => [[1], [null], [2]],
                'options' => [],
                'sortOrder' => 0
            ]
        ]);

        $block1 = Mockery::mock(Block::class)->makePartial();
        $block1->id = 1;
        $block1->page_id = 1;
        $block1->type = 'text';
        $block1->data = ['paragraphs' => ['Test 1']];

        $block2 = Mockery::mock(Block::class)->makePartial();
        $block2->id = 2;
        $block2->page_id = 1;
        $block2->type = 'text';
        $block2->data = ['paragraphs' => ['Test 2']];

        $this->blockRepository->shouldReceive('getPageBlocks')
            ->with(1)
            ->once()
            ->andReturn(collect([$block1, $block2]));

        $this->blockParserService->shouldReceive('buildBlock')
            ->twice()
            ->andReturn('<p>Content</p>');

        $result = $this->parser->buildZonesHtml($page);

        $this->assertEquals([1, 2], $result['usedBlockIds']);
    }

    public function testBuildZonesHtmlHandlesBlockRenderErrors()
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $page->zones = json_encode([
            [
                'id' => 'zone-a',
                'name' => 'Zone A',
                'columns' => 1,
                'blocks' => [[1]],
                'options' => [],
                'sortOrder' => 0
            ]
        ]);

        $block = Mockery::mock(Block::class)->makePartial();
        $block->id = 1;
        $block->page_id = 1;
        $block->type = 'text';
        $block->data = ['paragraphs' => ['Test']];

        $this->blockRepository->shouldReceive('getPageBlocks')
            ->with(1)
            ->once()
            ->andReturn(collect([$block]));

        $this->blockParserService->shouldReceive('buildBlock')
            ->once()
            ->andThrow(new \Exception('Render error'));

        $result = $this->parser->buildZonesHtml($page);

        // Should continue processing despite error
        $this->assertStringContainsString('zone-a', $result['html']);
        $this->assertEquals([], $result['usedBlockIds']); // Block wasn't successfully rendered
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->blockRepository = Mockery::mock(BlockRepository::class);
        $this->blockParserService = Mockery::mock(BlockParserService::class);

        $this->parser = new ZoneBlockParser(
            $this->blockRepository,
            $this->blockParserService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
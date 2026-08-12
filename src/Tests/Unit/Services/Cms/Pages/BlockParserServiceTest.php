<?php

namespace App\Tests\Unit\Services\Cms\Pages;

use App\Framework\Database\Database;
use App\Framework\Exceptions\BlockParserNotFoundException;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Validation\ValidationResult;
use App\Framework\Validation\Validator;
use App\Models\Block;
use App\Parsers\BlockFactory;
use App\Parsers\BlockRegistry;
use App\Parsers\BlockRendererManager;
use App\Parsers\TextBlockParser;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Services\Cms\Pages\BlockParserService;
use App\Tests\Unit\UnitTestCase;
use Mockery;

class BlockParserServiceTest extends UnitTestCase
{
    protected int $siteId = 1;

    private $blockRegistry;
    private $blockFactory;
    private $blockRendererManager;
    private $validator;
    private $blockRepository;
    private $pageRepository;
    private $databaseMock;
    private $service;

    protected function setUp(): void
    {
        $this->siteId = 1;

        $this->blockRegistry = Mockery::mock(BlockRegistry::class);
        $this->blockFactory = new BlockFactory();
        $this->blockRendererManager = Mockery::mock(BlockRendererManager::class);
        $this->validator = Mockery::mock(Validator::class);
        $this->blockRepository = Mockery::mock(BlockRepository::class);
        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);

        $this->service = new BlockParserService(
            $this->blockRegistry,
            $this->blockFactory,
            $this->blockRendererManager,
            $this->validator,
            $this->blockRepository,
            $this->pageRepository,
            $this->databaseMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testParseBlockValidatesBlockType()
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Block type is required');

        $blockData = ['data' => 'test'];

        $this->service->parseBlock(1, $blockData, 0);
    }

    public function testParseBlockThrowsExceptionWhenParserNotFound()
    {
        $this->expectException(BlockParserNotFoundException::class);

        $blockData = ['type' => 'nonexistent'];

        $this->blockRegistry->shouldReceive('getParser')
            ->with('nonexistent')
            ->andReturn(null);

        $this->service->parseBlock(1, $blockData, 0);
    }

    public function testParseBlockCreatesBlockSuccessfully()
    {
        $blockData = ['type' => 'text', 'paragraphs' => ['Hello'], 'context' => 'default'];

        $parser = Mockery::mock(TextBlockParser::class);
        $parser->shouldReceive('getValidationRules')->andReturn([]);

        $this->blockRegistry->shouldReceive('getParser')
            ->with('text')
            ->andReturn($parser);

        $validationResult = Mockery::mock(ValidationResult::class);
        $validationResult->shouldReceive('isValid')->andReturn(true);

        $this->validator->shouldReceive('validate')
            ->andReturn($validationResult);

        $block = Mockery::mock(Block::class);

        $this->blockRepository->shouldReceive('createBlock')
            ->once()
            ->andReturn($block);

        $result = $this->service->parseBlock(1, $blockData, 0);

        $this->assertInstanceOf(Block::class, $result);
    }

    public function testReplacePageBlocksDeletesAndRecreates()
    {
        $blocksData = [
            ['type' => 'text', 'paragraphs' => ['Block 1'], 'context' => 'default'],
            ['type' => 'text', 'paragraphs' => ['Block 2'], 'context' => 'default']
        ];

        $this->blockRepository->shouldReceive('deletePageBlocks')
            ->with(1)
            ->once();

        $parser = Mockery::mock(TextBlockParser::class);
        $parser->shouldReceive('getValidationRules')->andReturn([]);

        $this->blockRegistry->shouldReceive('getParser')
            ->andReturn($parser);

        $this->databaseMock->shouldReceive('transaction')
            ->atLeast()->once()
            ->andReturnUsing(fn($callback) => $callback());

        $validationResult = Mockery::mock(ValidationResult::class);
        $validationResult->shouldReceive('isValid')->andReturn(true);

        $this->validator->shouldReceive('validate')
            ->andReturn($validationResult);

        $this->blockRepository->shouldReceive('createBlock')
            ->twice()
            ->andReturn(Mockery::mock(Block::class));

        $result = $this->service->replacePageBlocks(1, $blocksData);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testUpdateBlockUpdatesExistingBlock()
    {
        $block = Mockery::mock(Block::class)->makePartial();
        $block->type = 'text';

        $this->blockRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($block);

        $parser = Mockery::mock(TextBlockParser::class);
        $parser->shouldReceive('getValidationRules')->andReturn([]);

        $this->blockRegistry->shouldReceive('getParser')
            ->with('text')
            ->andReturn($parser);

        $validationResult = Mockery::mock(ValidationResult::class);
        $validationResult->shouldReceive('isValid')->andReturn(true);

        $this->validator->shouldReceive('validate')
            ->andReturn($validationResult);

        $updatedBlock = Mockery::mock(Block::class);

        $this->blockRepository->shouldReceive('update')
            ->once()
            ->andReturn($updatedBlock);

        $result = $this->service->updateBlock(1, ['type' => 'text', 'paragraphs' => ['Updated'], 'context' => 'default']);

        $this->assertInstanceOf(Block::class, $result);
    }

    public function testParseBlockValidationFailsWithInvalidData()
    {
        $this->expectException(ValidationException::class);

        $blockData = ['type' => 'text', 'paragraphs' => []];

        $parser = Mockery::mock(TextBlockParser::class);
        $parser->shouldReceive('getValidationRules')->andReturn(['paragraphs' => 'required']);

        $this->blockRegistry->shouldReceive('getParser')
            ->with('text')
            ->andReturn($parser);

        $validationResult = Mockery::mock(ValidationResult::class);
        $validationResult->shouldReceive('isValid')->andReturn(false);
        $validationResult->shouldReceive('getErrors')->andReturn(['paragraphs' => ['Paragraphs are required']]);

        $this->validator->shouldReceive('validate')
            ->andReturn($validationResult);

        $this->service->parseBlock(1, $blockData, 0);
    }

    public function testReplacePageBlocksRollsBackOnError()
    {
        $blocksData = [
            ['type' => 'text', 'paragraphs' => ['Block 1'], 'context' => 'default'],
            ['type' => 'invalid', 'paragraphs' => ['Block 2']]
        ];

        $this->blockRepository->shouldReceive('deletePageBlocks')
            ->with(1)
            ->once();

        $parser = Mockery::mock(TextBlockParser::class);
        $parser->shouldReceive('getValidationRules')->andReturn([]);

        $this->blockRegistry->shouldReceive('getParser')
            ->with('text')
            ->andReturn($parser);

        $this->blockRegistry->shouldReceive('getParser')
            ->with('invalid')
            ->andReturn(null);

        $validationResult = Mockery::mock(ValidationResult::class);
        $validationResult->shouldReceive('isValid')->andReturn(true);

        $this->validator->shouldReceive('validate')
            ->andReturn($validationResult);

        $this->blockRepository->shouldReceive('createBlock')
            ->once()
            ->andReturn(Mockery::mock(Block::class));

        $this->databaseMock->shouldReceive('transaction')
            ->atLeast()->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->expectException(ValidationException::class);

        $this->service->replacePageBlocks(1, $blocksData);
    }

    public function testUpdateBlockThrowsExceptionWhenBlockNotFound()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Block not found');

        $this->blockRepository->shouldReceive('find')
            ->with(999)
            ->andReturn(null);

        $this->service->updateBlock(999, ['type' => 'text', 'content' => 'Updated']);
    }

    public function testUpdateBlockUsesExistingTypeWhenNotProvided()
    {
        $block = Mockery::mock(Block::class)->makePartial();
        $block->type = 'text';

        $this->blockRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($block);

        $parser = Mockery::mock(TextBlockParser::class);
        $parser->shouldReceive('getValidationRules')->andReturn([]);

        $this->blockRegistry->shouldReceive('getParser')
            ->with('text')
            ->andReturn($parser);

        $validationResult = Mockery::mock(ValidationResult::class);
        $validationResult->shouldReceive('isValid')->andReturn(true);

        $this->validator->shouldReceive('validate')
            ->andReturn($validationResult);

        $updatedBlock = Mockery::mock(Block::class);

        $this->blockRepository->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(function ($data) {
                return $data['type'] === 'text' && isset($data['data']);
            }))
            ->andReturn($updatedBlock);

        $result = $this->service->updateBlock(1, ['paragraphs' => ['Updated'], 'context' => 'default']);

        $this->assertInstanceOf(Block::class, $result);
    }

    public function testBuildBlockGeneratesHtml()
    {
        $blockData = ['type' => 'text', 'paragraphs' => ['Hello World'], 'context' => 'default'];

        $parser = Mockery::mock(TextBlockParser::class);
        $parser->shouldReceive('getValidationRules')->andReturn([]);

        $this->blockRegistry->shouldReceive('getParser')
            ->with('text')
            ->andReturn($parser);

        $validationResult = Mockery::mock(ValidationResult::class);
        $validationResult->shouldReceive('isValid')->andReturn(true);

        $this->validator->shouldReceive('validate')
            ->andReturn($validationResult);

        $this->blockRendererManager->shouldReceive('supports')
            ->andReturn(true);
        $this->blockRendererManager->shouldReceive('render')
            ->andReturn('<p>Hello World</p>');

        $result = $this->service->buildBlock(1, $blockData, 0, false, $this->siteId);

        $this->assertEquals('<p>Hello World</p>', $result);
    }

    public function testParsePageBlocksCollectsAllErrors()
    {
        $this->expectException(ValidationException::class);

        $blocksData = [
            ['type' => 'text'], // Missing content
            ['type' => 'nonexistent', 'content' => 'test'], // Invalid type
            ['type' => 'text', 'content' => ''] // Invalid content
        ];

        $parser = Mockery::mock(TextBlockParser::class);
        $parser->shouldReceive('getValidationRules')->andReturn(['content' => 'required']);

        $this->blockRegistry->shouldReceive('getParser')
            ->with('text')
            ->andReturn($parser);

        $this->blockRegistry->shouldReceive('getParser')
            ->with('nonexistent')
            ->andReturn(null);

        $this->databaseMock->shouldReceive('transaction')
            ->atLeast()->once()
            ->andReturnUsing(fn($callback) => $callback());

        $validationResult = Mockery::mock(ValidationResult::class);
        $validationResult->shouldReceive('isValid')->andReturn(false);
        $validationResult->shouldReceive('getErrors')
            ->andReturn(['content' => ['Content is required']]);

        $this->validator->shouldReceive('validate')
            ->andReturn($validationResult);

        $this->service->parsePageBlocks(1, $blocksData);
    }

    public function testParseMultiplePagesReturnsSuccessAndFailureCounts()
    {
        $pagesData = [
            [
                'page' => ['title' => 'Page 1', 'slug' => 'page-1', 'site_id' => 1],
                'blocks' => []
            ],
            [
                'page' => ['title' => 'Page 2', 'slug' => 'page-2', 'site_id' => 1],
                'blocks' => [['type' => 'invalid']]
            ]
        ];

        $this->databaseMock->shouldReceive('transaction')
            ->atLeast()->once()
            ->andReturnUsing(fn($callback) => $callback());

        $page = Mockery::mock(\App\Models\Page::class)->makePartial();
        $page->id = 1;
        $page->shouldReceive('toArray')->andReturn(['id' => 1, 'title' => 'Page 1']);

        $this->pageRepository->shouldReceive('create')
            ->atLeast()->once()
            ->andReturn($page);

        $this->blockRegistry->shouldReceive('getParser')
            ->with('invalid')
            ->andReturn(null);

        $result = $this->service->parseMultiplePages($pagesData);

        $this->assertInstanceOf(\App\Database\Seeders\DTO\BatchParseResult::class, $result);
        $this->assertEquals(1, $result->getSuccessCount());
        $this->assertEquals(1, $result->getFailedCount());
    }

    public function testParseBlockHandlesDealBlockMatching()
    {
        $blockData = [
            'type' => 'deal',
            'title' => 'Great Deal',
            'productName' => 'Test Product',
            'brand' => 'Test Brand',
            'price' => 99.99,
            'salePrice' => 79.99,
            'link' => 'https://example.com',
            'currency' => '$'
        ];

        $parser = Mockery::mock(\App\Parsers\DealBlockParser::class);
        $parser->shouldReceive('getValidationRules')->andReturn([]);
        $parser->shouldReceive('parse')->andReturn($blockData);

        $this->blockRegistry->shouldReceive('getParser')
            ->with('deal')
            ->andReturn($parser);

        $validationResult = Mockery::mock(ValidationResult::class);
        $validationResult->shouldReceive('isValid')->andReturn(true);

        $this->validator->shouldReceive('validate')
            ->andReturn($validationResult);

        $block = Mockery::mock(Block::class);

        $this->blockRepository->shouldReceive('createBlock')
            ->once()
            ->andReturn($block);

        $result = $this->service->parseBlock(1, $blockData, 0);

        $this->assertInstanceOf(Block::class, $result);
    }

    public function testParseBlockHandlesDealBlockCreation()
    {
        $blockData = [
            'type' => 'deal',
            'title' => 'New Deal',
            'productName' => 'New Product',
            'price' => 199.99,
            'link' => 'https://example.com',
            'currency' => '$'
        ];
        $parser = Mockery::mock(\App\Parsers\DealBlockParser::class);
        $parser->shouldReceive('getValidationRules')->andReturn([]);
        $parser->shouldReceive('parse')->andReturn($blockData);

        $this->blockRegistry->shouldReceive('getParser')
            ->with('deal')
            ->andReturn($parser);

        $validationResult = Mockery::mock(ValidationResult::class);
        $validationResult->shouldReceive('isValid')->andReturn(true);

        $this->validator->shouldReceive('validate')
            ->andReturn($validationResult);

        $block = Mockery::mock(Block::class);

        $this->blockRepository->shouldReceive('createBlock')
            ->once()
            ->andReturn($block);

        $result = $this->service->parseBlock(1, $blockData, 0);

        $this->assertInstanceOf(Block::class, $result);
    }

    public function testParseBlockSkipsProductMatchingWhenOptedOut()
    {
        $blockData = [
            'type' => 'deal',
            'productName' => 'Test Product',
            'price' => 99.99,
            'link' => 'https://example.com',
            'currency' => '$',
            'opted_out_product_match' => true
        ];
        $parser = Mockery::mock(\App\Parsers\DealBlockParser::class);
        $parser->shouldReceive('getValidationRules')->andReturn([]);
        $parser->shouldReceive('parse')->andReturn($blockData);

        $this->blockRegistry->shouldReceive('getParser')
            ->with('deal')
            ->andReturn($parser);

        $validationResult = Mockery::mock(ValidationResult::class);
        $validationResult->shouldReceive('isValid')->andReturn(true);

        $this->validator->shouldReceive('validate')
            ->andReturn($validationResult);

        $block = Mockery::mock(Block::class);

        $this->blockRepository->shouldReceive('createBlock')
            ->once()
            ->with(1, 'deal', Mockery::on(function ($data) {
                return !isset($data['product_id']) && $data['opted_out_product_match'] === true;
            }), 0)
            ->andReturn($block);

        $result = $this->service->parseBlock(1, $blockData, 0);

        $this->assertInstanceOf(Block::class, $result);
    }

}
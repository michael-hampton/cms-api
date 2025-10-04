<?php

namespace App\Tests\Unit\Services;

use App\Framework\Database\Database;
use App\Framework\Exceptions\BlockParserNotFoundException;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Validation\ValidationResult;
use App\Framework\Validation\Validator;
use App\Models\Block;
use App\Parsers\BlockRegistry;
use App\Parsers\TextBlockParser;
use App\Repositories\BlockRepository;
use App\Repositories\PageRepository;
use App\Services\BlockParserService;
use Mockery;
use PHPUnit\Framework\TestCase;

class BlockParserServiceTest extends TestCase
{
    private $blockRegistry;
    private $validator;
    private $blockRepository;
    private $pageRepository;
    private $database;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRegistry = Mockery::mock(BlockRegistry::class);
        $this->validator = Mockery::mock(Validator::class);
        $this->blockRepository = Mockery::mock(BlockRepository::class);
        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->database = Mockery::mock(Database::class);

        $this->service = new BlockParserService(
            $this->blockRegistry,
            $this->validator,
            $this->blockRepository,
            $this->pageRepository,
            $this->database
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
        $blockData = ['type' => 'text', 'content' => 'Hello'];

        $parser = Mockery::mock(TextBlockParser::class);;
        $parser->shouldReceive('getValidationRules')->andReturn([]);
        $parser->shouldReceive('parse')->andReturn(['content' => 'Hello']);

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
            ['type' => 'text', 'content' => 'Block 1'],
            ['type' => 'text', 'content' => 'Block 2']
        ];

        $this->database->shouldReceive('beginTransaction');

        $this->blockRepository->shouldReceive('deletePageBlocks')
            ->with(1)
            ->once();

        $parser = Mockery::mock(TextBlockParser::class);
        $parser->shouldReceive('getValidationRules')->andReturn([]);
        $parser->shouldReceive('parse')->andReturn(['content' => 'test']);

        $this->blockRegistry->shouldReceive('getParser')
            ->andReturn($parser);

        $validationResult = Mockery::mock(ValidationResult::class);
        $validationResult->shouldReceive('isValid')->andReturn(true);

        $this->validator->shouldReceive('validate')
            ->andReturn($validationResult);

        $this->blockRepository->shouldReceive('createBlock')
            ->twice()
            ->andReturn(Mockery::mock(Block::class));

        $this->database->shouldReceive('commit');

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

        $parser = Mockery::mock(TextBlockParser::class);;
        $parser->shouldReceive('getValidationRules')->andReturn([]);
        $parser->shouldReceive('parse')->andReturn(['content' => 'Updated']);

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

        $result = $this->service->updateBlock(1, ['type' => 'text', 'content' => 'Updated']);

        $this->assertInstanceOf(Block::class, $result);
    }
}
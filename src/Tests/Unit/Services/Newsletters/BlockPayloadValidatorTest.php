<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Services\Newsletter\Services\BlockDataFactory;
use App\Services\Newsletter\Validation\BlockPayloadValidator;
use PHPUnit\Framework\TestCase;

class BlockPayloadValidatorTest extends TestCase
{
    private BlockPayloadValidator $validator;

    public function test_accepts_known_block_types(): void
    {
        $this->validator->validate([
            ['type' => 'text', 'data' => ['paragraphs' => ['Hello']]],
            ['type' => 'heading', 'data' => ['text' => 'Title', 'level' => 2]],
            ['type' => 'divider', 'data' => ['style' => 'solid']],
        ]);

        $this->assertTrue(true); // no exception = pass
    }

    public function test_accepts_empty_block_array(): void
    {
        $this->validator->validate([]);
        $this->assertTrue(true);
    }

    public function test_rejects_unknown_block_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown block type/');

        $this->validator->validate([
            ['type' => 'completely_made_up', 'data' => []]
        ]);
    }

    public function test_rejects_block_missing_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/missing a type/');

        $this->validator->validate([
            ['data' => ['paragraphs' => ['hi']]]
        ]);
    }

    public function test_includes_index_in_error_message(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/index 1/');

        $this->validator->validate([
            ['type' => 'text', 'data' => ['paragraphs' => ['ok']]],
            ['type' => 'bad_type', 'data' => []],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new BlockPayloadValidator(new BlockDataFactory());
    }
}
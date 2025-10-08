<?php

namespace App\Tests\Unit\Parsers;

use App\Parsers\ListBlockParser;
use PHPUnit\Framework\TestCase;

class ListBlockParserTest extends TestCase
{
    public function testListParserGetType(): void
    {
        $parser = new ListBlockParser();
        $this->assertSame('list', $parser->getType());
    }

    public function testListParserParse(): void
    {
        $parser = new ListBlockParser();
        $data = ['listType' => 'ol', 'startIndex' => 2, 'items' => ['A', 'B']];
        $parsed = $parser->parse($data);
        $this->assertSame(2, $parsed['startIndex']);
        $this->assertSame(2, $parsed['total_word_count']);
    }
}
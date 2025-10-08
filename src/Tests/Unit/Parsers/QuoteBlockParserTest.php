<?php

namespace App\Tests\Unit\Parsers;

use App\Parsers\QuoteBlockParser;
use PHPUnit\Framework\TestCase;

class QuoteBlockParserTest extends TestCase
{
    public function testQuoteParserGetType(): void
    {
        $parser = new QuoteBlockParser();
        $this->assertSame('quote', $parser->getType());
    }

    public function testQuoteParserParse(): void
    {
        $parser = new QuoteBlockParser();
        $data = [
            'text' => 'Test quote',
            'attribution' => 'Test Author'
        ];
        $parsed = $parser->parse($data);
        $this->assertSame(2, $parsed['word_count']);
        $this->assertTrue($parsed['has_attribution']);
    }
}
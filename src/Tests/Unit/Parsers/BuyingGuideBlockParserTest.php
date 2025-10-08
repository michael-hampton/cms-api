<?php

namespace App\Tests\Unit\Parsers;

use App\Parsers\BuyingGuideBlockParser;
use PHPUnit\Framework\TestCase;

class BuyingGuideBlockParserTest extends TestCase
{
    public function testBuyingGuideParserGetType(): void
    {
        $parser = new BuyingGuideBlockParser();
        $this->assertSame('buying-guide', $parser->getType());
    }

    public function testBuyingGuideParserParse(): void
    {
        $parser = new BuyingGuideBlockParser();
        $data = ['title' => 'T', 'specs' => [['text' => 'A', 'value' => 'B']]];
        $parsed = $parser->parse($data);
        $this->assertTrue($parsed['has_specs']);
    }
}
<?php

namespace App\Tests\Unit\Parsers;

use App\Parsers\ProductComparisonBlockParser;
use PHPUnit\Framework\TestCase;

class ProductComparisonBlockParserTest extends TestCase
{
    public function testProductComparisonParserGetType(): void
    {
        $parser = new ProductComparisonBlockParser();
        $this->assertSame('product-comparison', $parser->getType());
    }

    public function testProductComparisonParserParse(): void
    {
        $parser = new ProductComparisonBlockParser();
        $data = [
            'title' => 'C', 'productA' => 'A', 'productB' => 'B',
            'comparisons' => [['subtitle' => 'S', 'items' => ['V1', 'V2']]]
        ];
        $parsed = $parser->parse($data);
        $this->assertCount(1, $parsed['comparisons']);
    }
}
<?php

namespace App\Tests\Unit\Parsers;

use App\Parsers\DealBlockParser;
use PHPUnit\Framework\TestCase;

class DealBlockParserTest extends TestCase
{
    public function testDealParserGetType(): void
    {
        $parser = new DealBlockParser();
        $this->assertSame('deal', $parser->getType());
    }

    public function testDealParserParseSavingsCalculations(): void
    {
        $parser = new DealBlockParser();
        $data = ['price' => 100.00, 'salePrice' => 50.00, 'title' => 'T', 'productName' => 'P', 'currency' => '$'];
        $parsed = $parser->parse($data);
        $this->assertSame(50.00, $parsed['savings']);
        $this->assertSame(50.00, $parsed['savings_percent']);
    }
}
<?php

namespace App\Tests\Unit\Parsers;

use App\Parsers\SectionBlockParser;
use PHPUnit\Framework\TestCase;

class SectionBlockParserTest extends TestCase
{
    public function testSectionParserGetType(): void
    {
        $parser = new SectionBlockParser();
        $this->assertSame('section', $parser->getType());
    }

    public function testSectionParserParse(): void
    {
        $parser = new SectionBlockParser();
        $data = ['title' => 'Test', 'headingType' => 'h5'];
        $parsed = $parser->parse($data);
        $this->assertSame(5, $parsed['heading_level']);
    }
}
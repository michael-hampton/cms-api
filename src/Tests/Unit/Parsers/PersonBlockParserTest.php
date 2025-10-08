<?php

namespace App\Tests\Unit\Parsers;

use App\Parsers\PersonBlockParser;
use PHPUnit\Framework\TestCase;

class PersonBlockParserTest extends TestCase
{
    public function testPersonParserGetType(): void
    {
        $parser = new PersonBlockParser();
        $this->assertSame('person', $parser->getType());
    }

    public function testPersonParserParse(): void
    {
        $parser = new PersonBlockParser();
        $data = ['name' => 'N', 'twitter' => '@user'];
        $parsed = $parser->parse($data);
        $this->assertArrayHasKey('twitter', $parsed['social_links']);
    }
}
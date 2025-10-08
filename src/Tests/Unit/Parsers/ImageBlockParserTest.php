<?php

namespace App\Tests\Unit\Parsers;

use App\Parsers\ImageBlockParser;
use PHPUnit\Framework\TestCase;

class ImageBlockParserTest extends TestCase
{
    public function testImageParserGetType(): void
    {
        $parser = new ImageBlockParser();
        $this->assertSame('image', $parser->getType());
    }

    public function testImageParserParse(): void
    {
        $parser = new ImageBlockParser();
        $data = ['src' => '/img.jpg', 'alt' => 'Alt text'];
        $parsed = $parser->parse($data);
        $this->assertSame('jpeg', $parsed['image_type']);
    }
}
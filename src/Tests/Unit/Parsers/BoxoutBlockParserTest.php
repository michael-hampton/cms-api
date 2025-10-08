<?php

namespace App\Tests\Unit\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\BoxoutBlockParser;
use PHPUnit\Framework\TestCase;

class BoxoutBlockParserTest extends TestCase
{
    public function testBoxoutParserGetType(): void
    {
        $parser = new BoxoutBlockParser();
        $this->assertSame('note', $parser->getType());
    }

    public function testBoxoutParserGetValidationRules(): void
    {
        $parser = new BoxoutBlockParser();
        $rules = $parser->getValidationRules();

        $this->assertArrayHasKey('title', $rules);
        $this->assertContainsOnlyInstancesOf(RequiredRule::class, array_filter($rules['title'], fn($r) => $r instanceof RequiredRule));

        $this->assertArrayHasKey('paragraphs', $rules);
        $this->assertContainsOnlyInstancesOf(ArrayRule::class, array_filter($rules['paragraphs'], fn($r) => $r instanceof ArrayRule));
    }

    public function testBoxoutParserParse(): void
    {
        $parser = new BoxoutBlockParser();
        $data = [
            'title' => 'A Note',
            'paragraphs' => ['Para 1', 'Para 2'],
            'image' => '/img.jpg'
        ];
        $parsed = $parser->parse($data);
        $this->assertSame(4, $parsed['word_count']); // 'A Note Para 1 Para 2'
        $this->assertTrue($parsed['has_image']);
    }

    public function testBoxoutParserGenerateHtml(): void
    {
        $parser = new BoxoutBlockParser();
        $parsedData = [
            'formatted_title' => 'Title',
            'formatted_paragraphs' => ['P1'],
            'image' => '/img.jpg',
            'has_image' => true
        ];
        $html = $parser->generateHtml($parsedData);
        $this->assertStringContainsString('<div class="note-block">', $html);
        $this->assertStringContainsString('<h4 class="note-title">Title</h4>', $html);
    }
}
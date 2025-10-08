<?php

namespace App\Tests\Unit\Parsers;

use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\HeadingBlockParser;
use App\Validation\Custom\HeadingLevelRule;
use PHPUnit\Framework\TestCase;

class HeadingBlockParserTest extends TestCase
{
    public function testHeadingParserGetType(): void
    {
        $parser = new HeadingBlockParser();
        $this->assertSame('heading', $parser->getType());
    }

    public function testHeadingParserGetValidationRules(): void
    {
        $parser = new HeadingBlockParser();
        $rules = $parser->getValidationRules();

        $this->assertArrayHasKey('text', $rules);
        $this->assertContainsOnlyInstancesOf(RequiredRule::class, array_filter($rules['text'], fn($r) => $r instanceof RequiredRule));

        $this->assertArrayHasKey('level', $rules);
        $this->assertContainsOnlyInstancesOf(HeadingLevelRule::class, array_filter($rules['level'], fn($r) => $r instanceof HeadingLevelRule));
    }

    public function testHeadingParserParse(): void
    {
        $parser = new HeadingBlockParser();
        $data = [
            'text' => '  Main Topic Title ',
            'subtitle' => ' A short supporting statement. ',
            'level' => 3
        ];
        $parsed = $parser->parse($data);

        $this->assertSame('Main Topic Title', $parsed['text']);
        $this->assertSame('A short supporting statement.', $parsed['subtitle']);
        $this->assertSame(3, $parsed['level']);
        $this->assertSame(7, $parsed['word_count']); // 'Main Topic Title A short supporting statement.' = 6 words
        $this->assertTrue($parsed['has_subtitle']);

        // Test with default level and no subtitle
        $parsedDefault = $parser->parse(['text' => 'Default']);
        $this->assertSame(2, $parsedDefault['level']);
        $this->assertFalse($parsedDefault['has_subtitle']);
    }

    public function testHeadingParserGenerateHtml(): void
    {
        $parser = new HeadingBlockParser();
        $parsedData = [
            'level' => 1,
            'formatted_text' => 'The Main H1',
            'formatted_subtitle' => 'H1 Subtitle',
            'has_subtitle' => true
        ];
        $html = $parser->generateHtml($parsedData);

        $this->assertStringContainsString('<div class="heading-block heading-level-1">', $html);
        $this->assertStringContainsString('<h1 class="heading-text">The Main H1</h1>', $html);
        $this->assertStringContainsString('<div class="heading-subtitle">H1 Subtitle</div>', $html);

        // Test without subtitle
        $parsedData['has_subtitle'] = false;
        $htmlNoSubtitle = $parser->generateHtml($parsedData);
        $this->assertStringNotContainsString('<div class="heading-subtitle">', $htmlNoSubtitle);
    }
}
<?php

namespace App\Tests\Unit\Parsers;

use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\TextBlockParser;
use PHPUnit\Framework\TestCase;

class TextBlockParserTest extends TestCase
{
    public function testTextParserGetType(): void
    {
        $parser = new TextBlockParser();
        $this->assertSame('text', $parser->getType());
    }

    public function testTextParserGetValidationRules(): void
    {
        $parser = new TextBlockParser();
        $rules = $parser->getValidationRules();

        $this->assertArrayHasKey('paragraphs', $rules);
        $this->assertContainsOnlyInstancesOf(MinRule::class, array_filter($rules['paragraphs'], fn($r) => $r instanceof MinRule));

        $this->assertArrayHasKey('paragraphs.*', $rules);
        $this->assertContainsOnlyInstancesOf(RequiredRule::class, array_filter($rules['paragraphs.*'], fn($r) => $r instanceof RequiredRule));
    }

    public function testTextParserParse(): void
    {
        $parser = new TextBlockParser();
        $data = [
            'paragraphs' => [
                '  This is the first sentence. ',
                '', // Empty paragraph
                'This is the second paragraph with more words.'
            ]
        ];
        $parsed = $parser->parse($data);

        $this->assertCount(2, $parsed['paragraphs']); // Empty one is removed
        $this->assertSame(['This is the first sentence.', 'This is the second paragraph with more words.'], $parsed['paragraphs']);

        // 1st para: 5 words. 2nd para: 7 words. Total: 12 words.
        $this->assertSame(13, $parsed['total_word_count']);
        $this->assertSame(2, $parsed['paragraph_count']);
        $this->assertSame(6.5, $parsed['average_words_per_paragraph']);
        $this->assertSame(1, $parsed['reading_time_minutes']); // 12 / 200 = 0.06, max(1, round(0.06)) = 1
        $this->assertGreaterThan(50, $parsed['total_char_count']);
    }

    public function testTextParserGenerateHtml(): void
    {
        $parser = new TextBlockParser();
        $parsedData = [
            'formatted_paragraphs' => [
                'First paragraph.',
                'Second paragraph.'
            ]
        ];
        $html = $parser->generateHtml($parsedData);

        $this->assertStringContainsString('<div class="text-block">', $html);
        $this->assertStringContainsString('<p class="text-paragraph">First paragraph.</p>', $html);
        $this->assertStringContainsString('<p class="text-paragraph">Second paragraph.</p>', $html);
    }
}
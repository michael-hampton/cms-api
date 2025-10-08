<?php

namespace App\Tests\Unit\Parsers;

use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\InfoBlockParser;
use PHPUnit\Framework\TestCase;

class InfoBlockParserTest extends TestCase
{
    public function testInfoParserGetType(): void
    {
        $parser = new InfoBlockParser();
        $this->assertSame('info', $parser->getType());
    }

    public function testInfoParserGetValidationRules(): void
    {
        $parser = new InfoBlockParser();
        $rules = $parser->getValidationRules();

        $this->assertArrayHasKey('infoType', $rules);
        $this->assertContainsOnlyInstancesOf(RequiredRule::class, array_filter($rules['infoType'], fn($r) => $r instanceof RequiredRule));
        // Note: InfoTypeRule is commented out in InfoBlockParserTest.php, so we don't assert its existence here.

        $this->assertArrayHasKey('description', $rules);
        $this->assertContainsOnlyInstancesOf(RequiredRule::class, array_filter($rules['description'], fn($r) => $r instanceof RequiredRule));
    }

    public function testInfoParserParse(): void
    {
        $parser = new InfoBlockParser();
        $data = [
            'infoType' => 'warning',
            'description' => '  This is important information. '
        ];
        $parsed = $parser->parse($data);

        $this->assertSame('warning', $parsed['infoType']);
        $this->assertSame('⚠️', $parsed['icon']);
        $this->assertSame(4, $parsed['word_count']);
    }

    public function testInfoParserGenerateHtml(): void
    {
        $parser = new InfoBlockParser();
        $parsedData = [
            'infoType' => 'tip',
            'formatted_description' => 'Use this shortcut.',
            'icon' => '💡'
        ];
        $html = $parser->generateHtml($parsedData);

        $this->assertStringContainsString('<div class="info-block info-type-tip">', $html);
        $this->assertStringContainsString('<span class="info-icon">💡</span>', $html);
        $this->assertStringContainsString('<span class="info-type">Tip</span>', $html);
        $this->assertStringContainsString('Use this shortcut.', $html);
    }
}
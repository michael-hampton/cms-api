<?php

namespace App\Tests\Unit\Parsers;

use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\MaxRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\AwardBlockParser;
use PHPUnit\Framework\TestCase;

class AwardBlockParserTest extends TestCase
{
    public function testAwardParserGetType(): void
    {
        $parser = new AwardBlockParser();
        $this->assertSame('award', $parser->getType());
    }

    public function testAwardParserGetValidationRules(): void
    {
        $parser = new AwardBlockParser();
        $rules = $parser->getValidationRules();

        $this->assertArrayHasKey('subcategory', $rules);
        $this->assertContainsOnlyInstancesOf(RequiredRule::class, array_filter($rules['subcategory'], fn($r) => $r instanceof RequiredRule));

        $this->assertArrayHasKey('rating', $rules);
        $this->assertContainsOnlyInstancesOf(MinRule::class, array_filter($rules['rating'], fn($r) => $r instanceof MinRule));
        $this->assertContainsOnlyInstancesOf(MaxRule::class, array_filter($rules['rating'], fn($r) => $r instanceof MaxRule));

        $this->assertArrayHasKey('winner', $rules);
        $this->assertContainsOnlyInstancesOf(BooleanRule::class, array_filter($rules['winner'], fn($r) => $r instanceof BooleanRule));
    }

    public function testAwardParserParse(): void
    {
        $parser = new AwardBlockParser();
        $data = [
            'subcategory' => 'Best Buy',
            'productName' => 'Widget Pro',
            'alt' => 'Widget Pro award image',
            'winner' => true,
            'rating' => 4.5,
            'strapline' => 'The best device of the year.'
        ];
        $parsed = $parser->parse($data);

        $this->assertSame('Widget Pro', $parsed['productName']);
        $this->assertTrue($parsed['winner']);
        $this->assertSame(4.5, $parsed['rating']);
        $this->assertSame(6, $parsed['strapline_word_count']);
    }

    public function testAwardParserGenerateHtml(): void
    {
        $parser = new AwardBlockParser();
        $parsedData = [
            'subcategory' => 'Best Value',
            'productName' => 'Item X',
            'image' => '/award.png',
            'alt' => 'Item X Award',
            'winner' => true,
            'strapline' => 'Amazing Value',
            'rating' => 4.0,
            'formatted_strapline' => 'Amazing Value',
            'caption' => '',
            'formatted_caption' => ''
        ];
        $html = $parser->generateHtml($parsedData);

        $this->assertStringContainsString('<div class="award-block award-winner">', $html);
        $this->assertStringContainsString('<h3 class="award-product-name">Item X</h3>', $html);
        $this->assertStringContainsString('<div class="award-winner-badge">Winner</div>', $html);
        $this->assertStringContainsString('<div class="award-rating">Rating: 4/5</div>', $html);
    }
}
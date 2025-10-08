<?php

namespace App\Tests\Unit\Parsers;

use App\Framework\Validation\Rules\MinLengthRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\UrlRule;
use App\Parsers\ProductBlockParser;
use App\Validation\Custom\SalePriceValidatorRule;
use PHPUnit\Framework\TestCase;

class ProductBlockParserTest extends TestCase
{
    public function testProductParserGetType(): void
    {
        $parser = new ProductBlockParser();
        $this->assertSame('product', $parser->getType());
    }

    public function testProductParserGetValidationRules(): void
    {
        $parser = new ProductBlockParser();
        $rules = $parser->getValidationRules();

        $this->assertArrayHasKey('link', $rules);
        $this->assertContainsOnlyInstancesOf(UrlRule::class, array_filter($rules['link'], fn($r) => $r instanceof UrlRule));

        $this->assertArrayHasKey('name', $rules);
        $this->assertContainsOnlyInstancesOf(MinLengthRule::class, array_filter($rules['name'], fn($r) => $r instanceof MinLengthRule));

        $this->assertArrayHasKey('price', $rules);
        $this->assertContainsOnlyInstancesOf(MinRule::class, array_filter($rules['price'], fn($r) => $r instanceof MinRule));

        $this->assertArrayHasKey('salePrice', $rules);
        $this->assertContainsOnlyInstancesOf(SalePriceValidatorRule::class, array_filter($rules['salePrice'], fn($r) => $r instanceof SalePriceValidatorRule));
    }

    public function testProductParserParse(): void
    {
        $parser = new ProductBlockParser();
        $data = [
            'name' => 'Widget 5',
            'productName' => 'Widget 5',
            'link' => 'http://example.com/buy',
            'price' => 200.0,
            'salePrice' => 150.0,
            'description' => 'Great product.',
            'review' => ['pros' => ['Fast'], 'rating' => 5.0],
            'showReviewPanel' => true
        ];
        $parsed = $parser->parse($data);

        $this->assertTrue($parsed['has_sale_price']);
        $this->assertSame(2, $parsed['description_word_count']);
        $this->assertNotNull($parsed['review']);
        $this->assertTrue($parsed['showReviewPanel']);
        $this->assertSame('Buy Now', $parsed['linkText']);
    }

    public function testProductParserGenerateHtml(): void
    {
        $parser = new ProductBlockParser();
        $parsedData = [
            'link' => 'http://buy.com',
            'noFollow' => true,
            'sponsored' => false,
            'openInNewTab' => true,
            'displayAs' => 'button',
            'linkText' => 'Get Yours',
            'image' => '/prod.jpg',
            'name' => 'The Gadget',
            'brand' => 'Acme',
            'productName' => 'The Gadget',
            'currency' => '$',
            'price' => 100.0,
            'salePrice' => 80.0,
            'layout' => 'compact',
            'description' => 'A small product.',
            'showReviewPanel' => true,
            'review' => ['pros' => ['Good']],
            'has_sale_price' => true,
            'description_word_count' => 3,
            'formatted_description' => 'A small product.'
        ];
        $html = $parser->generateHtml($parsedData);

        $this->assertStringContainsString('<div class="product-block product-layout-compact">', $html);
        $this->assertStringContainsString('<h3 class="product-name">The Gadget</h3>', $html);
        $this->assertStringContainsString('<span class="product-price original">$100</span>', $html);
        $this->assertStringContainsString('<span class="product-price sale">$80</span>', $html);
        $this->assertStringContainsString('<div class="product-review-panel">', $html);
        $this->assertStringContainsString('rel="nofollow" target="_blank"', $html);
        $this->assertStringContainsString('Get Yours</a>', $html);
    }
}
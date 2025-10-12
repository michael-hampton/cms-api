<?php

namespace App\Tests\Unit\Parsers;

use App\Enums\Currency;
use App\Enums\DisplayAs;
use App\Framework\Validation\Rules\MinLengthRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\UrlRule;
use App\Framework\Validation\Validator;
use App\Parsers\ProductBlockParser;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Validation\Custom\SalePriceValidatorRule;
use PHPUnit\Framework\TestCase;

class ProductBlockParserTest extends FunctionalTestCase
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
            'image' => ['src' => '/prod.jpg'],
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

    public function testProductBlockParserCalculatesSalePrice()
    {
        $parser = new ProductBlockParser();
        $data = [
            'name' => 'Product',
            'productName' => 'Product Name',
            'link' => 'https://example.com',
            'price' => 200,
            'salePrice' => 150
        ];

        $result = $parser->parse($data);

        $this->assertTrue($result['has_sale_price']);
        $this->assertEquals(150, $result['salePrice']);
    }

    public function testProductBlockParserRequiredFields()
    {
        $parser = new ProductBlockParser();
        $validator = new Validator();

        $data = [
            // Missing required fields: link, name, productName, price
            'brand' => 'Brand'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testProductBlockParserInvalidLink()
    {
        $parser = new ProductBlockParser();
        $validator = new Validator();

        $data = [
            'link' => 'not_a_valid_url',
            'name' => 'Product',
            'productName' => 'Product Name',
            'price' => 100
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testProductBlockParserNameMinLength()
    {
        $parser = new ProductBlockParser();
        $validator = new Validator();

        $data = [
            'link' => 'https://example.com',
            'name' => 'a', // Less than 2
            'productName' => 'Product Name',
            'price' => 100
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testProductBlockParserNameMaxLength()
    {
        $parser = new ProductBlockParser();
        $validator = new Validator();

        $data = [
            'link' => 'https://example.com',
            'name' => str_repeat('a', 256),
            'productName' => 'Product Name',
            'price' => 100
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testProductBlockParserProductNameMinLength()
    {
        $parser = new ProductBlockParser();
        $validator = new Validator();

        $data = [
            'link' => 'https://example.com',
            'name' => 'Product',
            'productName' => 'a', // Less than 2
            'price' => 100
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testProductBlockParserBrandMaxLength()
    {
        $parser = new ProductBlockParser();
        $validator = new Validator();

        $data = [
            'link' => 'https://example.com',
            'name' => 'Product',
            'productName' => 'Product Name',
            'brand' => str_repeat('a', 256),
            'price' => 100
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testProductBlockParserInvalidCurrency()
    {
        $parser = new ProductBlockParser();
        $validator = new Validator();

        $data = [
            'link' => 'https://example.com',
            'name' => 'Product',
            'productName' => 'Product Name',
            'currency' => 'INVALID',
            'price' => 100
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testProductBlockParserValidCurrencies()
    {
        $parser = new ProductBlockParser();

        foreach (Currency::cases() as $currency) {
            $data = [
                'link' => 'https://example.com',
                'name' => 'Product',
                'productName' => 'Product Name',
                'currency' => $currency->value,
                'price' => 100
            ];

            $result = $parser->parse($data);
            $this->assertEquals($currency->value, $result['currency']);
        }
    }

    public function testProductBlockParserPriceMinimum()
    {
        $parser = new ProductBlockParser();
        $validator = new Validator();

        $data = [
            'link' => 'https://example.com',
            'name' => 'Product',
            'productName' => 'Product Name',
            'price' => 0 // Less than 0.01
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testProductBlockParserSalePriceMinimum()
    {
        $parser = new ProductBlockParser();
        $validator = new Validator();

        $data = [
            'link' => 'https://example.com',
            'name' => 'Product',
            'productName' => 'Product Name',
            'price' => 100,
            'salePrice' => -1
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testProductBlockParserInvalidLayout()
    {
        $parser = new ProductBlockParser();
        $validator = new Validator();

        $data = [
            'link' => 'https://example.com',
            'name' => 'Product',
            'productName' => 'Product Name',
            'price' => 100,
            'layout' => 'invalid_layout'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testProductBlockParserValidLayouts()
    {
        $parser = new ProductBlockParser();

        $validLayouts = ['standard', 'compact', 'wide'];
        foreach ($validLayouts as $layout) {
            $data = [
                'link' => 'https://example.com',
                'name' => 'Product',
                'productName' => 'Product Name',
                'price' => 100,
                'layout' => $layout
            ];

            $result = $parser->parse($data);
            $this->assertEquals($layout, $result['layout']);
        }
    }

    public function testProductBlockParserInvalidDisplayAs()
    {
        $parser = new ProductBlockParser();
        $validator = new Validator();

        $data = [
            'link' => 'https://example.com',
            'name' => 'Product',
            'productName' => 'Product Name',
            'price' => 100,
            'displayAs' => 'invalid_display'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testProductBlockParserValidDisplayAs()
    {
        $parser = new ProductBlockParser();

        foreach (DisplayAs::cases() as $displayAs) {
            $data = [
                'link' => 'https://example.com',
                'name' => 'Product',
                'productName' => 'Product Name',
                'price' => 100,
                'displayAs' => $displayAs->value
            ];

            $result = $parser->parse($data);
            $this->assertEquals($displayAs->value, $result['displayAs']);
        }
    }

    public function testProductBlockParserDescriptionMaxLength()
    {
        $parser = new ProductBlockParser();
        $validator = new Validator();

        $data = [
            'link' => 'https://example.com',
            'name' => 'Product',
            'productName' => 'Product Name',
            'price' => 100,
            'description' => str_repeat('a', 1001)
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testProductBlockParserLinkTextMaxLength()
    {
        $parser = new ProductBlockParser();
        $validator = new Validator();

        $data = [
            'link' => 'https://example.com',
            'name' => 'Product',
            'productName' => 'Product Name',
            'price' => 100,
            'linkText' => str_repeat('a', 101)
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testProductBlockParserBooleanFields()
    {
        $parser = new ProductBlockParser();
        $validator = new Validator();

        $data = [
            'link' => 'https://example.com',
            'name' => 'Product',
            'productName' => 'Product Name',
            'price' => 100,
            'noFollow' => 'not_boolean',
            'sponsored' => 'not_boolean',
            'openInNewTab' => 'not_boolean'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testProductBlockParserImageArray()
    {
        $parser = new ProductBlockParser();
        $validator = new Validator();

        $data = [
            'link' => 'https://example.com',
            'name' => 'Product',
            'productName' => 'Product Name',
            'price' => 100,
            'image' => 'not_an_array'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }
}
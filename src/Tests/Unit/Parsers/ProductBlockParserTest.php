<?php

namespace App\Tests\Unit\Parsers;

use App\Enums\Blocks\DisplayAs;
use App\Enums\Currency;
use App\Framework\Validation\Rules\MinLengthRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\UrlRule;
use App\Framework\Validation\Validator;
use App\Parsers\ProductBlockParser;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Validation\Custom\SalePriceValidatorRule;

class ProductBlockParserTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ProductBlockParser();
        $this->validator = new Validator();
    }

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
            'formatted_description' => 'A small product.',
            'product_id' => 1
        ];
        $html = $parser->generateHtml($parsedData);

        $this->assertStringContainsString('<div class="product-card-container"><div class="product-card"', $html);
        $this->assertStringContainsString(
            'data-product-id="product-',
            $html
        );
        $this->assertStringContainsString('<h3 class="product-name"><a href="http://buy.com">The Gadget</a></h3>', $html);
        $this->assertStringContainsString('<div class="product-price"><span class="price-sale">$80.00</span><span class="price-original">$100.00</span></div>', $html);
        $this->assertStringContainsString('<a href="http://buy.com" class="btn-add-to-cart btn-primary" rel="nofollow" target="_blank">Get Yours</a>', $html);
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

    public function testRequiredFieldsValidation()
    {
        $data = [
            'brand' => 'Brand'
        ];

        $rules = $this->parser->getValidationRules();
        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
        $errors = $result->getErrors();
        $this->assertArrayHasKey('link', $errors);
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('productName', $errors);
        $this->assertArrayHasKey('price', $errors);
    }

    public function testInvalidLinkValidation()
    {
        $data = [
            'link' => 'not_a_valid_url',
            'name' => 'Product',
            'productName' => 'Product Name',
            'price' => 100
        ];

        $rules = $this->parser->getValidationRules();
        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('link', $result->getErrors());
    }

    public function testValidLinkValidation()
    {
        $validUrls = [
            'https://example.com',
            'http://example.com/product',
            'https://shop.example.com/item?id=123',
            'http://localhost:8000/product'
        ];

        foreach ($validUrls as $url) {
            $data = [
                'link' => $url,
                'name' => 'Product',
                'productName' => 'Product Name',
                'price' => 100
            ];

            $rules = $this->parser->getValidationRules();
            $result = $this->validator->validate($data, $rules);

            $this->assertTrue($result->isValid(), "URL {$url} should be valid");
        }
    }

    public function testNameMinLengthValidation()
    {
        $data = [
            'link' => 'https://example.com',
            'name' => 'a',
            'productName' => 'Product Name',
            'price' => 100
        ];

        $rules = $this->parser->getValidationRules();
        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('name', $result->getErrors());
    }

    public function testProductNameMinLengthValidation()
    {
        $data = [
            'link' => 'https://example.com',
            'name' => 'Product',
            'productName' => 'a',
            'price' => 100
        ];

        $rules = $this->parser->getValidationRules();
        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('productName', $result->getErrors());
    }

    public function testBrandMaxLengthValidation()
    {
        $data = [
            'link' => 'https://example.com',
            'name' => 'Product',
            'productName' => 'Product Name',
            'brand' => str_repeat('a', 256),
            'price' => 100
        ];

        $rules = $this->parser->getValidationRules();
        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('brand', $result->getErrors());
    }

    public function testBooleanFieldsValidation()
    {
        $data = [
            'link' => 'https://example.com',
            'name' => 'Product',
            'productName' => 'Product Name',
            'price' => 100,
            'noFollow' => 'not_boolean',
            'sponsored' => 'not_boolean',
            'openInNewTab' => 'not_boolean'
        ];

        $rules = $this->parser->getValidationRules();
        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('noFollow', $result->getErrors());
        $this->assertArrayHasKey('sponsored', $result->getErrors());
        $this->assertArrayHasKey('openInNewTab', $result->getErrors());
    }

    public function testInvalidCurrencyValidation()
    {
        $data = [
            'link' => 'https://example.com',
            'name' => 'Product',
            'productName' => 'Product Name',
            'currency' => 'INVALID',
            'price' => 100
        ];

        $rules = $this->parser->getValidationRules();
        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('currency', $result->getErrors());
    }

    public function testValidCurrencies()
    {
        foreach (Currency::cases() as $currency) {
            $data = [
                'link' => 'https://example.com',
                'name' => 'Product',
                'productName' => 'Product Name',
                'currency' => $currency->value,
                'price' => 100
            ];

            $result = $this->parser->parse($data);
            $this->assertEquals($currency->value, $result['currency']);
        }
    }

    public function testInvalidLayoutValidation()
    {
        $data = [
            'link' => 'https://example.com',
            'name' => 'Product',
            'productName' => 'Product Name',
            'price' => 100,
            'layout' => 'invalid_layout'
        ];

        $rules = $this->parser->getValidationRules();
        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('layout', $result->getErrors());
    }

    public function testValidLayouts()
    {
        $validLayouts = ['standard', 'compact', 'wide'];
        foreach ($validLayouts as $layout) {
            $data = [
                'link' => 'https://example.com',
                'name' => 'Product',
                'productName' => 'Product Name',
                'price' => 100,
                'layout' => $layout
            ];

            $result = $this->parser->parse($data);
            $this->assertEquals($layout, $result['layout']);
        }
    }

    public function testInvalidDisplayAsValidation()
    {
        $data = [
            'link' => 'https://example.com',
            'name' => 'Product',
            'productName' => 'Product Name',
            'price' => 100,
            'displayAs' => 'invalid_display'
        ];

        $rules = $this->parser->getValidationRules();
        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('displayAs', $result->getErrors());
    }

    public function testValidDisplayAs()
    {
        foreach (DisplayAs::cases() as $displayAs) {
            $data = [
                'link' => 'https://example.com',
                'name' => 'Product',
                'productName' => 'Product Name',
                'price' => 100,
                'displayAs' => $displayAs->value
            ];

            $result = $this->parser->parse($data);
            $this->assertEquals($displayAs->value, $result['displayAs']);
        }
    }

    public function testProductParserCalculatesSalePrice()
    {
        $data = [
            'name' => 'Product',
            'productName' => 'Product Name',
            'link' => 'https://example.com',
            'price' => 200,
            'salePrice' => 150
        ];

        $result = $this->parser->parse($data);

        $this->assertTrue($result['has_sale_price']);
        $this->assertEquals(150, $result['salePrice']);
    }

    public function testProductParserNoSalePrice()
    {
        $data = [
            'name' => 'Product',
            'productName' => 'Product Name',
            'link' => 'https://example.com',
            'price' => 200,
            'salePrice' => 0
        ];

        $result = $this->parser->parse($data);

        $this->assertFalse($result['has_sale_price']);
    }

    public function testProductParserDefaultValues()
    {
        $data = [
            'name' => 'Product',
            'productName' => 'Product Name',
            'link' => 'https://example.com',
            'price' => 100
        ];

        $result = $this->parser->parse($data);

        $this->assertEquals('Buy Now', $result['linkText']);
        $this->assertEquals('button', $result['displayAs']);
        $this->assertEquals('$', $result['currency']);
        $this->assertEquals('standard', $result['layout']);
        $this->assertFalse($result['noFollow']);
        $this->assertFalse($result['sponsored']);
        $this->assertFalse($result['openInNewTab']);
    }


    public function testHtmlContainsBrand()
    {
        $parsedData = [
            'link' => 'http://example.com',
            'name' => 'Product',
            'brand' => 'Test Brand',
            'productName' => 'Product',
            'currency' => '$',
            'price' => 100,
            'salePrice' => 0,
            'layout' => 'standard',
            'description' => '',
            'showReviewPanel' => false,
            'review' => null,
            'has_sale_price' => false,
            'description_word_count' => 0,
            'formatted_description' => '',
            'linkText' => 'Buy Now',
            'displayAs' => 'button',
            'noFollow' => false,
            'sponsored' => false,
            'openInNewTab' => false,
            'image' => null,
            'product_id' => null,
            'variant_id' => null,
            'opted_out_product_match' => false
        ];

        $html = $this->parser->generateHtml($parsedData);

        $this->assertStringContainsString('Test Brand', $html);
        $this->assertStringContainsString('product-brand-tag', $html);
    }

    public function testHtmlContainsSponsoredBadge()
    {
        $parsedData = [
            'link' => 'http://example.com',
            'name' => 'Product',
            'brand' => '',
            'productName' => 'Product',
            'currency' => '$',
            'price' => 100,
            'salePrice' => 0,
            'layout' => 'standard',
            'description' => '',
            'showReviewPanel' => false,
            'review' => null,
            'has_sale_price' => false,
            'description_word_count' => 0,
            'formatted_description' => '',
            'linkText' => 'Buy Now',
            'displayAs' => 'button',
            'noFollow' => false,
            'sponsored' => true,
            'openInNewTab' => false,
            'image' => ['src' => '/test.jpg'],
            'product_id' => null,
            'variant_id' => null,
            'opted_out_product_match' => false
        ];

        $html = $this->parser->generateHtml($parsedData);

        $this->assertStringContainsString('Sponsored', $html);
        $this->assertStringContainsString('product-badge sponsored', $html);
    }

    public function testHtmlContainsReviewPanel()
    {
        $parsedData = [
            'link' => 'http://example.com',
            'name' => 'Product',
            'brand' => '',
            'productName' => 'Product',
            'currency' => '$',
            'price' => 100,
            'salePrice' => 0,
            'layout' => 'standard',
            'description' => '',
            'showReviewPanel' => true,
            'review' => [
                'pros' => ['Fast shipping', 'Good quality'],
                'cons' => ['Expensive'],
                'rating' => 4.5
            ],
            'has_sale_price' => false,
            'description_word_count' => 0,
            'formatted_description' => '',
            'linkText' => 'Buy Now',
            'displayAs' => 'button',
            'noFollow' => false,
            'sponsored' => false,
            'openInNewTab' => false,
            'image' => null,
            'product_id' => null,
            'variant_id' => null,
            'opted_out_product_match' => false
        ];

        $html = $this->parser->generateHtml($parsedData);

        $this->assertStringContainsString('back-section', $html);
        $this->assertStringContainsString('Review', $html);
        $this->assertStringContainsString('Pros:', $html);
        $this->assertStringContainsString('Cons:', $html);
        $this->assertStringContainsString('Fast shipping', $html);
        $this->assertStringContainsString('Expensive', $html);
        $this->assertStringContainsString('4.5 / 5', $html);
    }

//    public function testHtmlContainsWishlistButton()
//    {
//        $parsedData = [
//            'link' => 'http://example.com',
//            'name' => 'Product',
//            'brand' => '',
//            'productName' => 'Product',
//            'currency' => '$',
//            'price' => 100,
//            'salePrice' => 0,
//            'layout' => 'standard',
//            'description' => '',
//            'showReviewPanel' => false,
//            'review' => null,
//            'has_sale_price' => false,
//            'description_word_count' => 0,
//            'formatted_description' => '',
//            'linkText' => 'Buy Now',
//            'displayAs' => 'button',
//            'noFollow' => false,
//            'sponsored' => false,
//            'openInNewTab' => false
//        ];
//
//        $html = $this->parser->generateHtml($parsedData);
//
//        $this->assertStringContainsString('btn-wishlist', $html);
//        $this->assertStringContainsString('addToWishlist', $html);
//    }

    public function testCompleteProductWithAllFeatures()
    {
        $data = [
            'name' => 'Premium Widget',
            'productName' => 'Premium Widget',
            'brand' => 'Top Brand',
            'link' => 'https://shop.example.com/widget',
            'price' => 299.99,
            'salePrice' => 199.99,
            'currency' => '€',
            'layout' => 'wide',
            'displayAs' => 'link',
            'linkText' => 'Shop Now',
            'description' => 'The best widget on the market with premium features.',
            'image' => ['src' => '/images/widget.jpg', 'alt' => 'Premium Widget'],
            'noFollow' => true,
            'sponsored' => true,
            'openInNewTab' => true,
            'showReviewPanel' => true,
            'review' => [
                'pros' => ['Excellent quality', 'Fast delivery', 'Great support'],
                'cons' => ['High price'],
                'rating' => 4.8
            ]
        ];

        $parsed = $this->parser->parse($data);
        $html = $this->parser->generateHtml($parsed);

        // Check all elements are present
        $this->assertStringContainsString('Premium Widget', $html);
        $this->assertStringContainsString('Top Brand', $html);
        $this->assertStringContainsString('€199.99', $html);
        $this->assertStringContainsString('€299.99', $html);
        $this->assertStringContainsString('Sponsored', $html);
        $this->assertStringContainsString('Excellent quality', $html);
        $this->assertStringContainsString('High price', $html);
        $this->assertStringContainsString('4.8 / 5', $html);
        $this->assertStringContainsString('Shop Now', $html);
    }

    public function testProductCardHasFlipButton()
    {
        $parsedData = $this->getBasicParsedData();
        $html = $this->parser->generateHtml($parsedData);

        $this->assertStringContainsString('btn-flip', $html);
        $this->assertStringContainsString('View details', $html);
    }

    public function testProductCardHasBackSide()
    {
        $parsedData = $this->getBasicParsedData();
        $html = $this->parser->generateHtml($parsedData);

        $this->assertStringContainsString('product-card-front', $html);
        $this->assertStringContainsString('product-card-back', $html);
        $this->assertStringContainsString('btn-flip-back', $html);
    }

    public function testProductCardBackContainsDescription()
    {
        $parsedData = $this->getBasicParsedData();
        $parsedData['description'] = 'This is a test description';
        $html = $this->parser->generateHtml($parsedData);

        $this->assertStringContainsString('card-back-content', $html);
        $this->assertStringContainsString('This is a test description', $html);
    }

    private function getBasicParsedData(): array
    {
        return [
            'link' => 'http://example.com',
            'name' => 'Product',
            'brand' => '',
            'productName' => 'Product',
            'currency' => '$',
            'price' => 100,
            'salePrice' => 0,
            'layout' => 'standard',
            'description' => '',
            'showReviewPanel' => false,
            'review' => null,
            'has_sale_price' => false,
            'description_word_count' => 0,
            'formatted_description' => '',
            'linkText' => 'Buy Now',
            'displayAs' => 'button',
            'noFollow' => false,
            'sponsored' => false,
            'openInNewTab' => false,
            'image' => null,
            'product_id' => null,
            'variant_id' => null,
            'opted_out_product_match' => false
        ];
    }
}
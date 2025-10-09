<?php

namespace App\Tests\Unit\Parsers;

use App\Framework\Validation\Validator;
use App\Parsers\BuyingGuideBlockParser;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use PHPUnit\Framework\TestCase;

class BuyingGuideBlockParserTest extends FunctionalTestCase
{
    public function testBuyingGuideParserGetType(): void
    {
        $parser = new BuyingGuideBlockParser();
        $this->assertSame('buying-guide', $parser->getType());
    }

    public function testBuyingGuideParserParse(): void
    {
        $parser = new BuyingGuideBlockParser();
        $data = ['title' => 'T', 'specs' => [['text' => 'A', 'value' => 'B']]];
        $parsed = $parser->parse($data);
        $this->assertTrue($parsed['has_specs']);
    }

    public function testBuyingGuideBlockParserValidData()
    {
        $parser = new BuyingGuideBlockParser();
        $data = [
            'title' => 'Buying Guide',
            'subtitle' => 'Everything you need',
            'url' => 'https://shop.com',
            'image' => ['url' => 'guide.jpg'],
            'specs' => [
                ['text' => 'Weight', 'value' => '500g'],
                ['text' => 'Color', 'value' => 'Blue']
            ],
            'pros' => ['Pro 1', 'Pro 2'],
            'cons' => ['Con 1'],
            'showReviewPanel' => true
        ];

        $result = $parser->parse($data);

        $this->assertEquals('Buying Guide', $result['title']);
        $this->assertCount(2, $result['specs']);
        $this->assertTrue($result['has_specs']);
        $this->assertTrue($result['has_pros_cons']);
        $this->assertTrue($result['showReviewPanel']);
        $this->assertTrue($result['has_image']);
    }

    public function testBuyingGuideBlockParserGeneratesHtmlWithImage()
    {
        $parser = new BuyingGuideBlockParser();
        $parsed = [
            'title' => 'Guide',
            'subtitle' => 'Subtitle',
            'url' => 'https://example.com',
            'linkText' => 'Buy Now',
            'image' => 'guide.jpg',
            'has_image' => true,
            'specs' => [['text' => 'Size', 'value' => 'Large']],
            'pros' => ['Good'],
            'cons' => ['Bad'],
            'showReviewPanel' => true,
            'has_specs' => true,
            'has_pros_cons' => true,
            'noFollow' => false,
            'sponsored' => false,
            'openInNewTab' => false
        ];

        $html = $parser->generateHtml($parsed);

        $this->assertStringContainsString('buying-guide-block', $html);
        $this->assertStringContainsString('buying-guide-image', $html);
        $this->assertStringContainsString('guide.jpg', $html);
        $this->assertStringContainsString('Specifications', $html);
    }

    public function testBuyingGuideBlockParserRequiredFields()
    {
        $parser = new BuyingGuideBlockParser();
        $validator = new Validator();

        $data = [
            // Missing required field: title
            'subtitle' => 'Subtitle'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testBuyingGuideBlockParserTitleMaxLength()
    {
        $parser = new BuyingGuideBlockParser();
        $validator = new Validator();

        $data = [
            'title' => str_repeat('a', 256)
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testBuyingGuideBlockParserSubtitleMaxLength()
    {
        $parser = new BuyingGuideBlockParser();
        $validator = new Validator();

        $data = [
            'title' => 'Title',
            'subtitle' => str_repeat('a', 501)
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testBuyingGuideBlockParserInvalidUrl()
    {
        $parser = new BuyingGuideBlockParser();
        $validator = new Validator();

        $data = [
            'title' => 'Title',
            'url' => 'not_a_valid_url'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testBuyingGuideBlockParserArrayFields()
    {
        $parser = new BuyingGuideBlockParser();
        $validator = new Validator();

        $data = [
            'title' => 'Title',
            'specs' => 'not_an_array',
            'pros' => 'not_an_array',
            'cons' => 'not_an_array'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testBuyingGuideBlockParserShowReviewPanelBoolean()
    {
        $parser = new BuyingGuideBlockParser();
        $validator = new Validator();

        $data = [
            'title' => 'Title',
            'showReviewPanel' => 'not_boolean'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testBuyingGuideBlockParserImageArray()
    {
        $parser = new BuyingGuideBlockParser();
        $validator = new Validator();

        $data = [
            'title' => 'Title',
            'image' => 'not_an_array'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }
}
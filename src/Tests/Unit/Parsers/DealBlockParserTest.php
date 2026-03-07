<?php

namespace App\Tests\Unit\Parsers;

use App\Enums\Currency;
use App\Framework\Validation\Validator;
use App\Parsers\DealBlockParser;
use App\Parsers\Dtos\DealBlockDto;
use App\Parsers\Renderers\DealBlockRenderer;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class DealBlockParserTest extends FunctionalTestCase
{
    public function testDealParserGetType(): void
    {
        $parser = new DealBlockParser();
        $this->assertSame('deal', $parser->getType());
    }

    public function testDealBlockParserCalculatesSavings()
    {
        $parser = new DealBlockParser();
        $data = [
            'title' => 'Great Deal',
            'productName' => 'Product',
            'link' => 'https://example.com',
            'price' => 100,
            'salePrice' => 75,
            'currency' => '£'
        ];

        $dto = DealBlockDto::fromArray($data);
        $result = $dto->toArray();

        $this->assertEquals(25, $result['savings']);
        $this->assertEquals(25, $result['savings_percent']);
        $this->assertTrue($result['has_savings']);
    }

    public function testDealBlockParserGeneratesHtml()
    {
        $parsed = [
            'title' => 'Deal',
            'productName' => 'Product',
            'brand' => 'Brand',
            'description' => 'Description',
            'price' => 100,
            'salePrice' => 75,
            'currency' => '£',
            'link' => 'https://example.com',
            'image' => ['src' => 'product.jpg'],
            'showDealButton' => true,
            'has_savings' => true,
            'savings' => 25,
            'savings_percent' => 25,
            'formatted_description' => 'Description',
            'link_attributes' => []
        ];

        $dto = DealBlockDto::fromArray($parsed);
        $renderer = new DealBlockRenderer();
        $html = $renderer->render($dto);

        $this->assertStringContainsString('deal-block', $html);
        $this->assertStringContainsString('£100', $html);
        $this->assertStringContainsString('£75', $html);
        $this->assertStringContainsString('Save £25', $html);
    }

    public function testDealBlockParserRejectsInvalidCurrency()
    {
        $parser = new DealBlockParser();
        $validator = new Validator();

        $data = [
            'title' => 'Deal',
            'productName' => 'Product',
            'link' => 'https://example.com',
            'price' => 100,
            'currency' => 'INVALID'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testDealBlockParserRequiredFields()
    {
        $parser = new DealBlockParser();
        $validator = new Validator();

        $data = [
            // Missing required fields: link, title, productName, currency, price
            'brand' => 'Brand'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testDealBlockParserInvalidUrl()
    {
        $parser = new DealBlockParser();
        $validator = new Validator();

        $data = [
            'link' => 'not_a_valid_url',
            'title' => 'Title',
            'productName' => 'Product',
            'currency' => '£',
            'price' => 100
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testDealBlockParserTitleMaxLength()
    {
        $parser = new DealBlockParser();
        $validator = new Validator();

        $data = [
            'link' => 'https://example.com',
            'title' => str_repeat('a', 256),
            'productName' => 'Product',
            'currency' => '£',
            'price' => 100
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testDealBlockParserProductNameMinLength()
    {
        $parser = new DealBlockParser();
        $validator = new Validator();

        $data = [
            'link' => 'https://example.com',
            'title' => 'Title',
            'productName' => 'a', // Less than 2
            'currency' => '£',
            'price' => 100
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testDealBlockParserProductNameMaxLength()
    {
        $parser = new DealBlockParser();
        $validator = new Validator();

        $data = [
            'link' => 'https://example.com',
            'title' => 'Title',
            'productName' => str_repeat('a', 256),
            'currency' => '£',
            'price' => 100
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testDealBlockParserInvalidCurrency()
    {
        $parser = new DealBlockParser();
        $validator = new Validator();

        $data = [
            'link' => 'https://example.com',
            'title' => 'Title',
            'productName' => 'Product',
            'currency' => 'INVALID',
            'price' => 100
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testDealBlockParserValidCurrencies()
    {
        $parser = new DealBlockParser();

        foreach (Currency::cases() as $currency) {
            $data = [
                'link' => 'https://example.com',
                'title' => 'Title',
                'productName' => 'Product',
                'currency' => $currency->value,
                'price' => 100
            ];

            $dto = DealBlockDto::fromArray($data);
            $result = $dto->toArray();
            $this->assertEquals($currency->value, $result['currency']);
        }
    }

    public function testDealBlockParserPriceMinimum()
    {
        $parser = new DealBlockParser();
        $validator = new Validator();

        $data = [
            'link' => 'https://example.com',
            'title' => 'Title',
            'productName' => 'Product',
            'currency' => '£',
            'price' => 0 // Less than 0.01
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testDealBlockParserSalePriceMinimum()
    {
        $parser = new DealBlockParser();
        $validator = new Validator();

        $data = [
            'link' => 'https://example.com',
            'title' => 'Title',
            'productName' => 'Product',
            'currency' => '£',
            'price' => 100,
            'salePrice' => -1
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testDealBlockParserDescriptionMaxLength()
    {
        $parser = new DealBlockParser();
        $validator = new Validator();

        $data = [
            'link' => 'https://example.com',
            'title' => 'Title',
            'productName' => 'Product',
            'currency' => '£',
            'price' => 100,
            'description' => str_repeat('a', 1001)
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testDealBlockParserBooleanFields()
    {
        $parser = new DealBlockParser();
        $validator = new Validator();

        $data = [
            'link' => 'https://example.com',
            'title' => 'Title',
            'productName' => 'Product',
            'currency' => '£',
            'price' => 100,
            'noFollow' => 'not_boolean',
            'sponsored' => 'not_boolean',
            'openInNewTab' => 'not_boolean',
            'showDealButton' => 'not_boolean',
            'starBlock' => 'not_boolean'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testDealBlockParserWithVoucher()
    {
        $parser = new DealBlockParser();
        $data = [
            'title' => 'Deal',
            'productName' => 'Product',
            'link' => 'https://example.com',
            'price' => 100,
            'currency' => '£',
            'voucherId' => 'SAVE20'
        ];

        $result = $parser->parse($data);

        $this->assertEquals('SAVE20', $result['voucherId']);
        $this->assertTrue($result['has_voucher']);
    }

    public function testDealBlockParserWithoutVoucher()
    {
        $parser = new DealBlockParser();
        $data = [
            'title' => 'Deal',
            'productName' => 'Product',
            'link' => 'https://example.com',
            'price' => 100,
            'currency' => '£'
        ];

        $result = $parser->parse($data);

        $this->assertEquals('', $result['voucherId']);
        $this->assertFalse($result['has_voucher']);
    }
}
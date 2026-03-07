<?php

namespace App\Tests\Unit\Parsers;

use App\Framework\Validation\Validator;
use App\Parsers\Dtos\ProductComparisonBlockDto;
use App\Parsers\ProductComparisonBlockParser;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class ProductComparisonBlockParserTest extends FunctionalTestCase
{
    public function testProductComparisonParserGetType(): void
    {
        $parser = new ProductComparisonBlockParser();
        $this->assertSame('product-comparison', $parser->getType());
    }

    public function testProductComparisonParserParse(): void
    {
        $parser = new ProductComparisonBlockParser();
        $data = [
            'title' => 'C', 'productA' => 'A', 'productB' => 'B',
            'comparisons' => [['subtitle' => 'S', 'items' => ['V1', 'V2']]]
        ];
        $dto = ProductComparisonBlockDto::fromArray($data);
        $parsed = $dto->toArray();
        $this->assertCount(1, $parsed['comparisons']);
    }

    public function testProductComparisonBlockParserRequiredFields()
    {
        $parser = new ProductComparisonBlockParser();
        $validator = new Validator();

        $data = [
            // Missing required fields: title, productA, productB, comparisons
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testProductComparisonBlockParserTitleMaxLength()
    {
        $parser = new ProductComparisonBlockParser();
        $validator = new Validator();

        $data = [
            'title' => str_repeat('a', 256),
            'productA' => 'Product A',
            'productB' => 'Product B',
            'comparisons' => []
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testProductComparisonBlockParserProductAMaxLength()
    {
        $parser = new ProductComparisonBlockParser();
        $validator = new Validator();

        $data = [
            'title' => 'Comparison',
            'productA' => str_repeat('a', 256),
            'productB' => 'Product B',
            'comparisons' => []
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testProductComparisonBlockParserProductBMaxLength()
    {
        $parser = new ProductComparisonBlockParser();
        $validator = new Validator();

        $data = [
            'title' => 'Comparison',
            'productA' => 'Product A',
            'productB' => str_repeat('a', 256),
            'comparisons' => []
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testProductComparisonBlockParserComparisonsArray()
    {
        $parser = new ProductComparisonBlockParser();
        $validator = new Validator();

        $data = [
            'title' => 'Comparison',
            'productA' => 'Product A',
            'productB' => 'Product B',
            'comparisons' => 'not_an_array'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testProductComparisonBlockParserComparisonValidation()
    {
        $parser = new ProductComparisonBlockParser();
        $validator = new Validator();

        $comparisonRules = $parser->getComparisonValidationRules();

        // Test comparison with missing required subtitle
        $comparisonData = [
            'items' => [
                ['value' => 'Value 1'],
                ['value' => 'Value 2']
            ]
        ];

        $result = $validator->validate($comparisonData, $comparisonRules);
        $this->assertFalse($result->isValid());
    }

    public function testProductComparisonBlockParserComparisonSubtitleMaxLength()
    {
        $parser = new ProductComparisonBlockParser();
        $validator = new Validator();

        $comparisonRules = $parser->getComparisonValidationRules();

        $comparisonData = [
            'subtitle' => str_repeat('a', 256),
            'items' => []
        ];

        $result = $validator->validate($comparisonData, $comparisonRules);
        $this->assertFalse($result->isValid());
    }

    public function testProductComparisonBlockParserComparisonItemsArray()
    {
        $parser = new ProductComparisonBlockParser();
        $validator = new Validator();

        $comparisonRules = $parser->getComparisonValidationRules();

        $comparisonData = [
            'subtitle' => 'Subtitle',
            'items' => 'not_an_array'
        ];

        $result = $validator->validate($comparisonData, $comparisonRules);
        $this->assertFalse($result->isValid());
    }
}
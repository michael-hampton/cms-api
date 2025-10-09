<?php

namespace App\Tests\Unit\Parsers;

use App\Framework\Validation\Validator;
use App\Parsers\QuoteBlockParser;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use PHPUnit\Framework\TestCase;

class QuoteBlockParserTest extends FunctionalTestCase
{
    public function testQuoteParserGetType(): void
    {
        $parser = new QuoteBlockParser();
        $this->assertSame('quote', $parser->getType());
    }

    public function testQuoteParserParse(): void
    {
        $parser = new QuoteBlockParser();
        $data = [
            'text' => 'Test quote',
            'attribution' => 'Test Author'
        ];
        $parsed = $parser->parse($data);
        $this->assertSame(2, $parsed['word_count']);
        $this->assertTrue($parsed['has_attribution']);
    }

    public function testQuoteBlockParserRequiredFields()
    {
        $parser = new QuoteBlockParser();
        $validator = new Validator();

        $data = [
            // Missing required field: text
            'attribution' => 'Author'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testQuoteBlockParserTextMaxLength()
    {
        $parser = new QuoteBlockParser();
        $validator = new Validator();

        $data = [
            'text' => str_repeat('a', 1001)
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testQuoteBlockParserAttributionMaxLength()
    {
        $parser = new QuoteBlockParser();
        $validator = new Validator();

        $data = [
            'text' => 'Quote text',
            'attribution' => str_repeat('a', 256)
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }
}
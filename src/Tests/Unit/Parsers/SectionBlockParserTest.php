<?php

namespace App\Tests\Unit\Parsers;

use App\Framework\Validation\Validator;
use App\Parsers\Dtos\SectionBlockDto;
use App\Parsers\SectionBlockParser;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class SectionBlockParserTest extends FunctionalTestCase
{
    public function testSectionParserGetType(): void
    {
        $parser = new SectionBlockParser();
        $this->assertSame('section', $parser->getType());
    }

    public function testSectionParserParse(): void
    {
        $parser = new SectionBlockParser();
        $data = ['title' => 'Test', 'headingType' => 'h5'];
        $dto = SectionBlockDto::fromArray($data);
        $parsed = $dto->toArray();
        $this->assertSame(5, $parsed['heading_level']);
    }

    public function testSectionBlockParserRequiredFields()
    {
        $parser = new SectionBlockParser();
        $validator = new Validator();

        $data = [
            // Missing required fields: title, headingType, navigationText
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testSectionBlockParserTitleMaxLength()
    {
        $parser = new SectionBlockParser();
        $validator = new Validator();

        $data = [
            'title' => str_repeat('a', 256),
            'headingType' => 'h2',
            'navigationText' => 'Nav Text'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testSectionBlockParserNavigationTextMaxLength()
    {
        $parser = new SectionBlockParser();
        $validator = new Validator();

        $data = [
            'title' => 'Title',
            'headingType' => 'h2',
            'navigationText' => str_repeat('a', 256)
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testSectionBlockParserExcludeFromNavBoolean()
    {
        $parser = new SectionBlockParser();
        $validator = new Validator();

        $data = [
            'title' => 'Title',
            'headingType' => 'h2',
            'navigationText' => 'Nav Text',
            'excludeFromNav' => 'not_boolean'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }
}
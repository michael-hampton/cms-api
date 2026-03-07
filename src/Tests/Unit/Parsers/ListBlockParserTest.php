<?php

namespace App\Tests\Unit\Parsers;

use App\Enums\Blocks\ListType;
use App\Enums\Blocks\SchemaType;
use App\Framework\Validation\Validator;
use App\Parsers\Dtos\ListBlockDto;
use App\Parsers\ListBlockParser;
use App\Parsers\Renderers\ListBlockRenderer;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class ListBlockParserTest extends FunctionalTestCase
{
    public function testListParserGetType(): void
    {
        $parser = new ListBlockParser();
        $this->assertSame('list', $parser->getType());
    }

    public function testListParserParse(): void
    {
        $parser = new ListBlockParser();
        $data = ['listType' => 'ol', 'startIndex' => 2, 'items' => ['A', 'B']];
        $dto = ListBlockDto::fromArray($data);
        $parsed = $dto->toArray();
        $this->assertSame(2, $parsed['startIndex']);
        $this->assertSame(2, $parsed['total_word_count']);
    }

    public function testListBlockParserValidData()
    {
        $parser = new ListBlockParser();
        $data = [
            'listType' => 'ol',
            'startIndex' => 5,
            'schemaType' => 'steps',
            'items' => ['Item 1', 'Item 2', 'Item 3']
        ];

        $dto = ListBlockDto::fromArray($data);
        $result = $dto->toArray();

        $this->assertEquals('ol', $result['listType']);
        $this->assertEquals(5, $result['startIndex']);
        $this->assertEquals(3, $result['item_count']);
    }

    public function testListBlockParserGeneratesOrderedList()
    {
        $parsed = [
            'listType' => 'ol',
            'startIndex' => 3,
            'schemaType' => 'steps',
            'items' => ['First', 'Second'],
            'formatted_items' => ['First', 'Second'],
            'item_count' => 2,
            'total_word_count' => 2
        ];

        $dto = ListBlockDto::fromArray($parsed);
        $renderer = new ListBlockRenderer();
        $html = $renderer->render($dto);

        $this->assertStringContainsString('<ol', $html);
        $this->assertStringContainsString('start="3"', $html);
        $this->assertStringContainsString('list-schema-steps', $html);
    }

    public function testListBlockParserRejectsInvalidListType()
    {
        $parser = new ListBlockParser();
        $validator = new Validator();

        $data = [
            'listType' => 'dl', // Invalid, should be 'ul' or 'ol'
            'items' => ['Item 1', 'Item 2']
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testListBlockParserRequiredFields()
    {
        $parser = new ListBlockParser();
        $validator = new Validator();

        $data = [
            // Missing required fields: listType, items
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testListBlockParserInvalidListType()
    {
        $parser = new ListBlockParser();
        $validator = new Validator();

        $data = [
            'listType' => 'dl', // Invalid
            'items' => ['Item 1']
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testListBlockParserValidListTypes()
    {
        $parser = new ListBlockParser();

        foreach (ListType::cases() as $listType) {
            $data = [
                'listType' => $listType->value, 'items' => ['Item 1', 'Item 2']
            ];

            $dto = ListBlockDto::fromArray($data);
            $result = $dto->toArray();
            $this->assertEquals($listType->value, $result['listType']);
        }
    }

    public function testListBlockParserInvalidSchemaType()
    {
        $parser = new ListBlockParser();
        $validator = new Validator();

        $data = [
            'listType' => 'ul',
            'items' => ['Item 1'],
            'schemaType' => 'invalid_schema'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testListBlockParserValidSchemaTypes()
    {
        $parser = new ListBlockParser();

        foreach (SchemaType::cases() as $schemaType) {
            $data = [
                'listType' => 'ul',
                'items' => ['Item 1'],
                'schemaType' => $schemaType->value
            ];

            $dto = ListBlockDto::fromArray($data);
            $result = $dto->toArray();
            $this->assertEquals($schemaType->value, $result['schemaType']);
        }
    }

    public function testListBlockParserStartIndexInteger()
    {
        $parser = new ListBlockParser();
        $validator = new Validator();

        $data = [
            'listType' => 'ol',
            'items' => ['Item 1'],
            'startIndex' => 'not_an_integer'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testListBlockParserItemsArray()
    {
        $parser = new ListBlockParser();
        $validator = new Validator();

        $data = [
            'listType' => 'ul',
            'items' => 'not_an_array'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }
}
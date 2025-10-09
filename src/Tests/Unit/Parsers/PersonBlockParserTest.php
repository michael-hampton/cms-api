<?php

namespace App\Tests\Unit\Parsers;

use App\Enums\DisplayType;
use App\Framework\Validation\Validator;
use App\Parsers\PersonBlockParser;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use PHPUnit\Framework\TestCase;

class PersonBlockParserTest extends FunctionalTestCase
{
    public function testPersonParserGetType(): void
    {
        $parser = new PersonBlockParser();
        $this->assertSame('person', $parser->getType());
    }

    public function testPersonBlockParserValidData()
    {
        $parser = new PersonBlockParser();
        $data = [
            'name' => 'John Doe',
            'role' => 'Developer',
            'bio' => 'Experienced developer',
            'email' => 'john@example.com',
            'twitter' => '@johndoe',
            'displayType' => 'profile'
        ];

        $result = $parser->parse($data);

        $this->assertEquals('John Doe', $result['name']);
        $this->assertEquals('Developer', $result['role']);
        $this->assertArrayHasKey('social_links', $result);
        $this->assertEquals('profile', $result['displayType']);
    }

    public function testPersonBlockParserGeneratesProfileHtml()
    {
        $parser = new PersonBlockParser();
        $parsed = [
            'name' => 'Jane Smith',
            'role' => 'Designer',
            'bio' => 'Creative designer',
            'image' => 'jane.jpg',
            'email' => 'jane@example.com',
            'phone' => '123456',
            'formatted_name' => 'Jane Smith',
            'formatted_role' => 'Designer',
            'formatted_bio' => 'Creative designer',
            'displayType' => 'profile',
            'social_links' => [],
            'bio_word_count' => 2,
            'strapline_word_count' => 0
        ];

        $html = $parser->generateHtml($parsed);

        $this->assertStringContainsString('person-display-profile', $html);
        $this->assertStringContainsString('Jane Smith', $html);
        $this->assertStringContainsString('Designer', $html);
    }

    public function testPersonBlockParserGeneratesContactHtml()
    {
        $parser = new PersonBlockParser();
        $parsed = [
            'name' => 'Contact',
            'phone' => '123-456-7890',
            'email' => 'contact@example.com',
            'displayType' => 'contact',
            'formatted_name' => 'Contact',
            'social_links' => [],
            'bio_word_count' => 0,
            'strapline_word_count' => 0
        ];

        $html = $parser->generateHtml($parsed);

        $this->assertStringContainsString('person-display-contact', $html);
        $this->assertStringContainsString('Contact Information', $html);
        $this->assertStringContainsString('123-456-7890', $html);
    }

    public function testPersonBlockParserRejectsInvalidDisplayType()
    {
        $parser = new PersonBlockParser();
        $validator = new Validator();

        $data = [
            'name' => 'John Doe',
            'displayType' => 'invalid_type'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testPersonBlockParserRequiredFields()
    {
        $parser = new PersonBlockParser();
        $validator = new Validator();

        $data = [
            // Missing required field: name
            'role' => 'Developer'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testPersonBlockParserNameMaxLength()
    {
        $parser = new PersonBlockParser();
        $validator = new Validator();

        $data = [
            'name' => str_repeat('a', 256)
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testPersonBlockParserRoleMaxLength()
    {
        $parser = new PersonBlockParser();
        $validator = new Validator();

        $data = [
            'name' => 'John Doe',
            'role' => str_repeat('a', 256)
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testPersonBlockParserStraplineMaxLength()
    {
        $parser = new PersonBlockParser();
        $validator = new Validator();

        $data = [
            'name' => 'John Doe',
            'strapline' => str_repeat('a', 501)
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testPersonBlockParserBioMaxLength()
    {
        $parser = new PersonBlockParser();
        $validator = new Validator();

        $data = [
            'name' => 'John Doe',
            'bio' => str_repeat('a', 2001)
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testPersonBlockParserInvalidEmail()
    {
        $parser = new PersonBlockParser();
        $validator = new Validator();

        $data = [
            'name' => 'John Doe',
            'email' => 'not_a_valid_email'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testPersonBlockParserInvalidWebsite()
    {
        $parser = new PersonBlockParser();
        $validator = new Validator();

        $data = [
            'name' => 'John Doe',
            'website' => 'not_a_valid_url'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testPersonBlockParserInvalidDisplayType()
    {
        $parser = new PersonBlockParser();
        $validator = new Validator();

        $data = [
            'name' => 'John Doe',
            'displayType' => 'invalid_display'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testPersonBlockParserValidDisplayTypes()
    {
        $parser = new PersonBlockParser();

        foreach (DisplayType::cases() as $displayType) {
            $data = [
                'name' => 'John Doe',
                'displayType' => $displayType->value
            ];

            $result = $parser->parse($data);
            $this->assertEquals($displayType->value, $result['displayType']);
        }
    }

    public function testPersonBlockParserEnableSchemaBoolean()
    {
        $parser = new PersonBlockParser();
        $validator = new Validator();

        $data = [
            'name' => 'John Doe',
            'enableSchema' => 'not_boolean'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }
}
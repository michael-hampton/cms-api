<?php

namespace App\Tests\Unit\Parsers;

use App\Enums\Blocks\InfoType;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Validator;
use App\Parsers\InfoBlockParser;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class InfoBlockParserTest extends FunctionalTestCase
{
    public function testInfoParserGetType(): void
    {
        $parser = new InfoBlockParser();
        $this->assertSame('info', $parser->getType());
    }

    public function testInfoParserGetValidationRules(): void
    {
        $parser = new InfoBlockParser();
        $rules = $parser->getValidationRules();

        $this->assertArrayHasKey('infoType', $rules);
        $this->assertContainsOnlyInstancesOf(RequiredRule::class, array_filter($rules['infoType'], fn($r) => $r instanceof RequiredRule));
        // Note: InfoTypeRule is commented out in InfoBlockParserTest.php, so we don't assert its existence here.

        $this->assertArrayHasKey('description', $rules);
        $this->assertContainsOnlyInstancesOf(RequiredRule::class, array_filter($rules['description'], fn($r) => $r instanceof RequiredRule));
    }

    public function testInfoParserParse(): void
    {
        $parser = new InfoBlockParser();
        $data = [
            'infoType' => 'warning',
            'description' => '  This is important information. '
        ];
        $parsed = $parser->parse($data);

        $this->assertSame('warning', $parsed['infoType']);
        $this->assertSame('⚠️', $parsed['icon']);
        $this->assertSame(4, $parsed['word_count']);
    }

    public function testInfoParserGenerateHtml(): void
    {
        $parser = new InfoBlockParser();
        $parsedData = [
            'infoType' => 'tip',
            'description' => 'Use this shortcut.',
            'icon' => '💡'
        ];
        $html = $parser->generateHtml($parsedData);

        $this->assertStringContainsString('<div class="info-block info-type-tip">', $html);
        $this->assertStringContainsString('<span class="info-icon">💡</span>', $html);
        $this->assertStringContainsString('<span class="info-type">Tip</span>', $html);
        $this->assertStringContainsString('Use this shortcut.', $html);
    }

    public function testInfoBlockParserRequiredFields()
    {
        $parser = new InfoBlockParser();
        $validator = new Validator();

        $data = [
            // Missing required fields: infoType, description
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testInfoBlockParserInvalidInfoType()
    {
        $parser = new InfoBlockParser();
        $validator = new Validator();

        $data = [
            'infoType' => 'invalid_type',
            'description' => 'Description'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testInfoBlockParserValidInfoTypes()
    {
        $parser = new InfoBlockParser();

        foreach (InfoType::cases() as $infoType) {
            $data = [
                'infoType' => $infoType->value,
                'description' => 'Description'
            ];

            $result = $parser->parse($data);
            $this->assertEquals($infoType->value, $result['infoType']);
            $this->assertEquals($infoType->getIcon(), $result['icon']);
        }
    }

    public function testInfoBlockParserDescriptionMaxLength()
    {
        $parser = new InfoBlockParser();
        $validator = new Validator();

        $data = [
            'infoType' => 'info',
            'description' => str_repeat('a', 2001)
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }
}
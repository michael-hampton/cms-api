<?php

namespace App\Tests\Unit\Parsers;

use App\Enums\Blocks\HeadingLevel;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Validator;
use App\Parsers\Dtos\HeadingBlockDto;
use App\Parsers\HeadingBlockParser;
use App\Parsers\Renderers\HeadingBlockRenderer;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Validation\Custom\HeadingLevelRule;

class HeadingBlockParserTest extends FunctionalTestCase
{
    public function testHeadingParserGetType(): void
    {
        $parser = new HeadingBlockParser();
        $this->assertSame('heading', $parser->getType());
    }

    public function testHeadingParserGetValidationRules(): void
    {
        $parser = new HeadingBlockParser();
        $rules = $parser->getValidationRules();

        $this->assertArrayHasKey('text', $rules);
        $this->assertContainsOnlyInstancesOf(RequiredRule::class, array_filter($rules['text'], fn($r) => $r instanceof RequiredRule));

        $this->assertArrayHasKey('level', $rules);
        $this->assertContainsOnlyInstancesOf(HeadingLevelRule::class, array_filter($rules['level'], fn($r) => $r instanceof HeadingLevelRule));
    }

    public function testHeadingParserParse(): void
    {
        $parser = new HeadingBlockParser();
        $data = [
            'text' => '  Main Topic Title ',
            'subtitle' => ' A short supporting statement. ',
            'level' => 3
        ];
        $dto = HeadingBlockDto::fromArray($data);
        $parsed = $dto->toArray();

        $this->assertSame('Main Topic Title', $parsed['text']);
        $this->assertSame('A short supporting statement.', $parsed['subtitle']);
        $this->assertSame(3, $parsed['level']);
        $this->assertSame(7, $parsed['word_count']); // 'Main Topic Title A short supporting statement.' = 6 words
        $this->assertTrue($parsed['has_subtitle']);

        // Test with default level and no subtitle
        $dtoDefault = HeadingBlockDto::fromArray(['text' => 'Default']);
        $parsedDefault = $dtoDefault->toArray();
        $this->assertSame(2, $parsedDefault['level']);
        $this->assertFalse($parsedDefault['has_subtitle']);
    }

    public function testHeadingParserGenerateHtml(): void
    {
        $parsedData = [
            'level' => 1,
            'text' => 'The Main H1',
            'subtitle' => 'H1 Subtitle',
            'has_subtitle' => true
        ];
        $dto = HeadingBlockDto::fromArray($parsedData);
        $renderer = new HeadingBlockRenderer();
        $html = $renderer->render($dto);

        $this->assertStringContainsString('<div class="heading-block heading-level-1">', $html);
        $this->assertStringContainsString('<h1 class="heading-text">The Main H1</h1>', $html);
        $this->assertStringContainsString('<div class="heading-subtitle">H1 Subtitle</div>', $html);

        // Test without subtitle
        $parsedData['subtitle'] = '';
        $dtoNoSubtitle = HeadingBlockDto::fromArray($parsedData);
        $htmlNoSubtitle = $renderer->render($dtoNoSubtitle);
        $this->assertStringNotContainsString('<div class="heading-subtitle">', $htmlNoSubtitle);
    }

    public function testHeadingBlockParserRequiredFields()
    {
        $parser = new HeadingBlockParser();
        $validator = new Validator();

        $data = [
            // Missing required fields: text, level
            'subtitle' => 'Subtitle'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testHeadingBlockParserTextMaxLength()
    {
        $parser = new HeadingBlockParser();
        $validator = new Validator();

        $data = [
            'text' => str_repeat('a', 256),
            'level' => 2
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testHeadingBlockParserSubtitleMaxLength()
    {
        $parser = new HeadingBlockParser();
        $validator = new Validator();

        $data = [
            'text' => 'Heading',
            'subtitle' => str_repeat('a', 501),
            'level' => 2
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testHeadingBlockParserInvalidLevel()
    {
        $parser = new HeadingBlockParser();
        $validator = new Validator();

        $data = [
            'text' => 'Heading',
            'level' => 'invalid_level'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testHeadingBlockParserValidLevels()
    {
        $parser = new HeadingBlockParser();

        foreach (HeadingLevel::cases() as $level) {
            $data = [
                'text' => 'Heading',
                'level' => $level->value
            ];

            $dto = HeadingBlockDto::fromArray($data);
            $result = $dto->toArray();
            $this->assertEquals($level->getLevel(), $result['level']);
        }
    }
}
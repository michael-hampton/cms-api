<?php

namespace App\Tests\Unit\Parsers;

use App\Framework\Validation\Rules\RequiredIfRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\SchemaBlockDto;
use App\Parsers\Renderers\SchemaBlockRenderer;
use App\Parsers\SchemaBlockParser;
use PHPUnit\Framework\TestCase;

class SchemaBlockParserTest extends TestCase
{
    public function testSchemaParserGetType(): void
    {
        $parser = new SchemaBlockParser();
        $this->assertSame('schema', $parser->getType());
    }

    public function testSchemaParserGetValidationRules(): void
    {
        $parser = new SchemaBlockParser();
        $rules = $parser->getValidationRules();

        $this->assertArrayHasKey('schemaType', $rules);
        $this->assertContainsOnlyInstancesOf(RequiredRule::class, array_filter($rules['schemaType'], fn($r) => $r instanceof RequiredRule));

        $this->assertArrayHasKey('title', $rules);
        $this->assertContainsOnlyInstancesOf(RequiredIfRule::class, array_filter($rules['title'], fn($r) => $r instanceof RequiredIfRule));

        $this->assertArrayHasKey('question', $rules);
        $this->assertContainsOnlyInstancesOf(RequiredIfRule::class, array_filter($rules['question'], fn($r) => $r instanceof RequiredIfRule));
    }

    public function testSchemaParserParseHowTo(): void
    {
        $parser = new SchemaBlockParser();
        $data = [
            'schemaType' => 'how-to',
            'title' => '  How To Boil Water ',
            'description' => 'The easiest way to make tea.',
            'image' => ['src' => '/howto.jpg']
        ];
        $dto = SchemaBlockDto::fromArray($data);
        $parsed = $dto->toArray();

        $this->assertSame('how-to', $parsed['schemaType']);
        $this->assertSame('How To Boil Water', $parsed['title']);
        $this->assertSame(4, $parsed['title_word_count']);
        $this->assertSame(6, $parsed['description_word_count']);
        $this->assertArrayHasKey('description', $parsed);
    }

    public function testSchemaParserParseQuestion(): void
    {
        $parser = new SchemaBlockParser();
        $data = [
            'schemaType' => 'question',
            'question' => 'What is the capital of France?',
            'answer' => '  Paris ',
            'expansion' => 'A longer explanation of why Paris is the capital.'
        ];
        $dto = SchemaBlockDto::fromArray($data);
        $parsed = $dto->toArray();

        $this->assertSame('question', $parsed['schemaType']);
        $this->assertSame('What is the capital of France?', $parsed['question']);
        $this->assertSame('Paris', $parsed['answer']);
        $this->assertSame(6, $parsed['question_word_count']);
        $this->assertSame(1, $parsed['answer_word_count']);
        $this->assertSame(9, $parsed['expansion_word_count']);
        $this->assertTrue($parsed['showExpansion']);
        $this->assertArrayHasKey('answer', $parsed);
    }

    public function testSchemaParserGenerateHtmlHowTo(): void
    {
        $parsedData = [
            'schemaType' => 'how-to',
            'title' => 'Test How To',
            'formatted_description' => 'A brief guide.',
            'image' => ['src' => '/test.jpg']
        ];
        $dto = SchemaBlockDto::fromArray($parsedData);
        $renderer = new SchemaBlockRenderer();
        $html = $renderer->render($dto);

        $this->assertStringContainsString('<div class="schema-block schema-type-how-to">', $html);
        $this->assertStringContainsString('<img src="/test.jpg" alt="Test How To" class="schema-image">', $html);
        $this->assertStringContainsString('<h3 class="schema-title">Test How To</h3>', $html);
        $this->assertStringContainsString('<div class="schema-block schema-type-how-to"><div class="schema-howto-block"><img src="/test.jpg" alt="Test How To" class="schema-image"><h3 class="schema-title">Test How To</h3></div></div>', $html);
        $this->assertStringNotContainsString('schema-question', $html);
    }

    public function testSchemaParserGenerateHtmlQuestion(): void
    {
        $parsedData = [
            'schemaType' => 'question',
            'question' => 'Q?',
            'answer' => 'A.',
            'expansion' => 'E.',
            'showExpansion' => true
        ];
        $dto = SchemaBlockDto::fromArray($parsedData);
        $renderer = new SchemaBlockRenderer();
        $html = $renderer->render($dto);

        $this->assertStringContainsString('<div class="schema-block schema-type-question">', $html);
        $this->assertStringContainsString('<h3 class="schema-question">Q?</h3>', $html);
        $this->assertStringContainsString('<div class="schema-answer">A.</div>', $html);
        $this->assertStringContainsString('<div class="schema-block schema-type-question"><div class="schema-question-block"><h3 class="schema-question">Q?</h3><div class="schema-answer">A.</div><div class="schema-expansion">E.</div></div></div>', $html);
        $this->assertStringNotContainsString('schema-howto-block', $html);
    }
}
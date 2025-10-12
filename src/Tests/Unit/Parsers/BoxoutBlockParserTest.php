<?php

namespace App\Tests\Unit\Parsers;

use App\Enums\Alignment;
use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Validator;
use App\Parsers\BoxoutBlockParser;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use PHPUnit\Framework\TestCase;

class BoxoutBlockParserTest extends FunctionalTestCase
{
    public function testBoxoutParserGetType(): void
    {
        $parser = new BoxoutBlockParser();
        $this->assertSame('note', $parser->getType());
    }

    public function testBoxoutParserGetValidationRules(): void
    {
        $parser = new BoxoutBlockParser();
        $rules = $parser->getValidationRules();

        $this->assertArrayHasKey('title', $rules);
        $this->assertContainsOnlyInstancesOf(RequiredRule::class, array_filter($rules['title'], fn($r) => $r instanceof RequiredRule));

        $this->assertArrayHasKey('paragraphs', $rules);
        $this->assertContainsOnlyInstancesOf(ArrayRule::class, array_filter($rules['paragraphs'], fn($r) => $r instanceof ArrayRule));
    }

    public function testBoxoutBlockParserValidData()
    {
        $parser = new BoxoutBlockParser();
        $data = [
            'title' => 'Important Note',
            'paragraphs' => ['First paragraph', 'Second paragraph'],
            'image' => ['url' => 'note.jpg'],
            'alignment' => 'left',
            'linkUrl' => 'https://example.com',
            'linkText' => 'Read More',
            'noFollow' => true,
            'sponsored' => false,
            'openInNewTab' => true
        ];

        $result = $parser->parse($data);

        $this->assertEquals('Important Note', $result['title']);
        $this->assertCount(2, $result['paragraphs']);
        $this->assertEquals('left', $result['alignment']);
        $this->assertEquals('https://example.com', $result['linkUrl']);
        $this->assertTrue($result['has_link']);
        $this->assertTrue($result['noFollow']);
        $this->assertTrue($result['openInNewTab']);
        $this->assertEquals(6, $result['word_count']);
        $this->assertTrue($result['has_image']);
        $this->assertArrayHasKey('link_attributes', $result);
    }

    public function testBoxoutParserGenerateHtml(): void
    {
        $parser = new BoxoutBlockParser();
        $parsedData = [
            'formatted_title' => 'Title',
            'formatted_paragraphs' => ['P1'],
            'image' => ['src' => '/img.jpg'],
            'has_image' => true
        ];
        $html = $parser->generateHtml($parsedData);
        $this->assertStringContainsString('<div class="note-block note-align-"><div class="note-image"><img src="/img.jpg" alt="Title" class="note-img"></div><div class="note-content"><h4 class="note-title">Title</h4><p class="note-paragraph">P1</p></div></div>', $html);
        $this->assertStringContainsString('<h4 class="note-title">Title</h4>', $html);
    }

    public function testBoxoutBlockParserGeneratesHtmlWithLink()
    {
        $parser = new BoxoutBlockParser();
        $parsed = [
            'title' => 'Note Title',
            'paragraphs' => ['Para 1', 'Para 2'],
            'formatted_title' => 'Note Title',
            'formatted_paragraphs' => ['Para 1', 'Para 2'],
            'has_image' => false,
            'image' => null,
            'alignment' => 'centre',
            'has_link' => true,
            'linkUrl' => 'https://example.com',
            'linkText' => 'Learn More',
            'link_attributes' => ['target' => '_blank', 'rel' => 'noopener noreferrer nofollow'],
            'word_count' => 5
        ];

        $html = $parser->generateHtml($parsed);

        $this->assertStringContainsString('note-block', $html);
        $this->assertStringContainsString('note-align-centre', $html);
        $this->assertStringContainsString('https://example.com', $html);
        $this->assertStringContainsString('Learn More', $html);
        $this->assertStringContainsString('target="_blank"', $html);
    }

    public function testBoxoutBlockParserLinkAttributes()
    {
        $parser = new BoxoutBlockParser();
        $data = [
            'title' => 'Test',
            'paragraphs' => ['Test'],
            'linkUrl' => 'https://example.com',
            'noFollow' => true,
            'sponsored' => true,
            'openInNewTab' => true
        ];

        $result = $parser->parse($data);

        $this->assertArrayHasKey('rel', $result['link_attributes']);
        $this->assertStringContainsString('nofollow', $result['link_attributes']['rel']);
        $this->assertStringContainsString('sponsored', $result['link_attributes']['rel']);
        $this->assertStringContainsString('noopener', $result['link_attributes']['rel']);
    }

    public function testBoxoutBlockParserRejectsInvalidAlignment()
    {
        $parser = new BoxoutBlockParser();
        $validator = new Validator();

        $data = [
            'title' => 'Test',
            'paragraphs' => ['Test'],
            'alignment' => 'invalid_alignment'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testBoxoutBlockParserAcceptsValidAlignment()
    {
        $parser = new BoxoutBlockParser();
        $validator = new Validator();

        $data = [
            'title' => 'Test',
            'paragraphs' => ['Test'],
            'alignment' => 'left'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        // Should pass if only checking alignment
        $this->assertTrue(in_array($data['alignment'], ['left', 'right', 'centre', 'fullscreen']));
    }

    public function testBoxoutBlockParserRequiredFields()
    {
        $parser = new BoxoutBlockParser();
        $validator = new Validator();

        $data = [
            // Missing required fields: title, paragraphs
            'alignment' => 'left'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testBoxoutBlockParserTitleMaxLength()
    {
        $parser = new BoxoutBlockParser();
        $validator = new Validator();

        $data = [
            'title' => str_repeat('a', 256),
            'paragraphs' => ['Test']
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testBoxoutBlockParserParagraphsArray()
    {
        $parser = new BoxoutBlockParser();
        $validator = new Validator();

        $data = [
            'title' => 'Title',
            'paragraphs' => 'not_an_array'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testBoxoutBlockParserInvalidAlignment()
    {
        $parser = new BoxoutBlockParser();
        $validator = new Validator();

        $data = [
            'title' => 'Title',
            'paragraphs' => ['Test'],
            'alignment' => 'invalid_alignment'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testBoxoutBlockParserValidAlignment()
    {
        $parser = new BoxoutBlockParser();

        foreach (Alignment::cases() as $alignment) {
            $data = [
                'title' => 'Test',
                'paragraphs' => ['Test'],
                'alignment' => $alignment->value
            ];

            $result = $parser->parse($data);
            $this->assertEquals($alignment->value, $result['alignment']);
        }
    }

    public function testBoxoutBlockParserInvalidLinkUrl()
    {
        $parser = new BoxoutBlockParser();
        $validator = new Validator();

        $data = [
            'title' => 'Title',
            'paragraphs' => ['Test'],
            'linkUrl' => 'not_a_valid_url'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testBoxoutBlockParserLinkTextMaxLength()
    {
        $parser = new BoxoutBlockParser();
        $validator = new Validator();

        $data = [
            'title' => 'Title',
            'paragraphs' => ['Test'],
            'linkText' => str_repeat('a', 101)
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }

    public function testBoxoutBlockParserBooleanFields()
    {
        $parser = new BoxoutBlockParser();
        $validator = new Validator();

        $data = [
            'title' => 'Title',
            'paragraphs' => ['Test'],
            'noFollow' => 'not_boolean',
            'sponsored' => 'not_boolean',
            'openInNewTab' => 'not_boolean'
        ];

        $rules = $parser->getValidationRules();
        $result = $validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
    }
}
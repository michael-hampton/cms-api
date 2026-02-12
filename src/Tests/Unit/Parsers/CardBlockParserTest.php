<?php

namespace App\Tests\Unit\Parsers;

use App\Framework\Validation\Rules\MaxLengthRule;
use App\Parsers\CardBlockParser;
use PHPUnit\Framework\TestCase;

class CardBlockParserTest extends TestCase
{
    private CardBlockParser $parser;

    public function test_get_type_returns_card()
    {
        $this->assertEquals('card', $this->parser->getType());
    }

    public function test_get_validation_rules_returns_correct_rules()
    {
        $rules = $this->parser->getValidationRules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('title', $rules);
        $this->assertArrayHasKey('description', $rules);
        $this->assertArrayHasKey('linkUrl', $rules);
        $this->assertArrayHasKey('buttonType', $rules);
        $this->assertArrayHasKey('buttonText', $rules);

        // Check title max length
        $this->assertInstanceOf(MaxLengthRule::class, $rules['title'][0]);

        // Check description max length
        $this->assertInstanceOf(MaxLengthRule::class, $rules['description'][0]);
    }

    public function test_parse_basic_card_data()
    {
        $input = [
            'title' => 'Test Card',
            'description' => 'Test description',
            'linkUrl' => 'https://example.com',
            'buttonText' => 'Click Here',
            'buttonType' => 'primary'
        ];

        $result = $this->parser->parse($input);

        $this->assertEquals('Test Card', $result['title']);
        $this->assertEquals('Test description', $result['description']);
        $this->assertEquals('https://example.com', $result['linkUrl']);
        $this->assertEquals('Click Here', $result['buttonText']);
        $this->assertEquals('primary', $result['buttonType']);
        $this->assertFalse($result['noFollow']);
        $this->assertFalse($result['sponsored']);
        $this->assertFalse($result['openInNewTab']);
    }

    public function test_parse_with_empty_data()
    {
        $result = $this->parser->parse([]);

        $this->assertEquals('', $result['title']);
        $this->assertEquals('', $result['description']);
        $this->assertEquals('', $result['linkUrl']);
        $this->assertEquals('primary', $result['buttonType']);
        $this->assertEquals('Learn More', $result['buttonText']);
        $this->assertNull($result['image']);
        $this->assertNull($result['endorsement']);
        $this->assertNull($result['sponsorDeclaration']);
    }

    public function test_parse_with_image()
    {
        $input = [
            'title' => 'Card with Image',
            'image' => [
                'id' => '123',
                'src' => 'https://example.com/image.jpg',
                'name' => 'Test Image',
                'alt' => 'Test Alt',
                'caption' => 'Test Caption'
            ]
        ];

        $result = $this->parser->parse($input);

        $this->assertIsArray($result['image']);
        $this->assertEquals('123', $result['image']['id']);
        $this->assertEquals('https://example.com/image.jpg', $result['image']['src']);
        $this->assertEquals('Test Image', $result['image']['name']);
        $this->assertEquals('Test Alt', $result['image']['alt']);
        $this->assertEquals('Test Caption', $result['image']['caption']);
    }

    public function test_parse_with_empty_image_returns_null()
    {
        $input = [
            'title' => 'Card',
            'image' => []
        ];

        $result = $this->parser->parse($input);

        $this->assertNull($result['image']);
    }

    public function test_parse_with_endorsement()
    {
        $input = [
            'title' => 'Card',
            'endorsement' => [
                'src' => 'https://example.com/badge.png',
                'alt' => 'Featured Badge'
            ]
        ];

        $result = $this->parser->parse($input);

        $this->assertIsArray($result['endorsement']);
        $this->assertEquals('https://example.com/badge.png', $result['endorsement']['src']);
        $this->assertEquals('Featured Badge', $result['endorsement']['alt']);
    }

    public function test_parse_with_sponsor_declaration()
    {
        $input = [
            'title' => 'Sponsored Card',
            'sponsored' => true,
            'sponsorDeclaration' => [
                'sponsoredText' => 'Sponsored by',
                'sponsorName' => 'Acme Corp',
                'sponsorLogo' => [
                    'src' => 'https://example.com/logo.png',
                    'alt' => 'Acme Logo'
                ]
            ]
        ];

        $result = $this->parser->parse($input);

        $this->assertTrue($result['sponsored']);
        $this->assertIsArray($result['sponsorDeclaration']);
        $this->assertEquals('Sponsored by', $result['sponsorDeclaration']['sponsoredText']);
        $this->assertEquals('Acme Corp', $result['sponsorDeclaration']['sponsorName']);
        $this->assertIsArray($result['sponsorDeclaration']['sponsorLogo']);
    }

    public function test_parse_with_empty_sponsor_declaration_returns_null()
    {
        $input = [
            'title' => 'Card',
            'sponsorDeclaration' => []
        ];

        $result = $this->parser->parse($input);

        $this->assertNull($result['sponsorDeclaration']);
    }

    public function test_parse_validates_url_formats()
    {
        $testCases = [
            ['url' => 'https://example.com', 'expected' => 'https://example.com'],
            ['url' => 'http://example.com', 'expected' => 'http://example.com'],
            ['url' => '/relative/path', 'expected' => '/relative/path'],
            ['url' => '#anchor', 'expected' => '#anchor'],
            ['url' => 'invalid-url', 'expected' => ''],
            ['url' => 'javascript:alert(1)', 'expected' => '']
        ];

        foreach ($testCases as $case) {
            $result = $this->parser->parse(['linkUrl' => $case['url']]);
            $this->assertEquals($case['expected'], $result['linkUrl'],
                "Failed for URL: {$case['url']}");
        }
    }

    public function test_parse_boolean_flags()
    {
        $input = [
            'title' => 'Card',
            'noFollow' => true,
            'sponsored' => true,
            'openInNewTab' => true
        ];

        $result = $this->parser->parse($input);

        $this->assertTrue($result['noFollow']);
        $this->assertTrue($result['sponsored']);
        $this->assertTrue($result['openInNewTab']);
    }

    public function test_parse_converts_string_booleans()
    {
        $input = [
            'title' => 'Card',
            'noFollow' => '1',
            'sponsored' => 'true',
            'openInNewTab' => 1
        ];

        $result = $this->parser->parse($input);

        $this->assertTrue($result['noFollow']);
        $this->assertTrue($result['sponsored']);
        $this->assertTrue($result['openInNewTab']);
    }

    public function test_generate_html_basic_card()
    {
        $data = $this->parser->parse([
            'title' => 'Test Card',
            'description' => 'Test description',
            'linkUrl' => 'https://example.com',
            'buttonText' => 'Read More'
        ]);

        $html = $this->parser->generateHtml($data);

        $this->assertStringContainsString('card-block', $html);
        $this->assertStringContainsString('Test Card', $html);
        $this->assertStringContainsString('Test description', $html);
        $this->assertStringContainsString('https://example.com', $html);
        $this->assertStringContainsString('Read More', $html);
    }

    public function test_generate_html_with_image()
    {
        $data = $this->parser->parse([
            'title' => 'Card with Image',
            'linkUrl' => 'https://example.com',
            'image' => [
                'src' => 'https://example.com/image.jpg',
                'alt' => 'Test Image'
            ]
        ]);

        $html = $this->parser->generateHtml($data);

        $this->assertStringContainsString('card-image', $html);
        $this->assertStringContainsString('https://example.com/image.jpg', $html);
        $this->assertStringContainsString('Test Image', $html);
    }

    public function test_generate_html_with_endorsement()
    {
        $data = $this->parser->parse([
            'title' => 'Card',
            'image' => [
                'src' => 'https://example.com/image.jpg',
                'alt' => 'Image'
            ],
            'endorsement' => [
                'src' => 'https://example.com/badge.png',
                'alt' => 'Featured'
            ]
        ]);

        $html = $this->parser->generateHtml($data);

        $this->assertStringContainsString('card-endorsement', $html);
        $this->assertStringContainsString('https://example.com/badge.png', $html);
    }

    public function test_generate_html_with_sponsor_declaration()
    {
        $data = $this->parser->parse([
            'title' => 'Sponsored Card',
            'sponsorDeclaration' => [
                'sponsoredText' => 'Sponsored by',
                'sponsorName' => 'Acme Corp'
            ]
        ]);

        $html = $this->parser->generateHtml($data);

        $this->assertStringContainsString('card-sponsor-declaration', $html);
        $this->assertStringContainsString('Sponsored by', $html);
        $this->assertStringContainsString('Acme Corp', $html);
    }

    public function test_generate_html_link_attributes_with_nofollow()
    {
        $data = $this->parser->parse([
            'title' => 'Card',
            'linkUrl' => 'https://example.com',
            'noFollow' => true
        ]);

        $html = $this->parser->generateHtml($data);

        $this->assertStringContainsString('rel="nofollow"', $html);
    }

    public function test_generate_html_link_attributes_with_sponsored()
    {
        $data = $this->parser->parse([
            'title' => 'Card',
            'linkUrl' => 'https://example.com',
            'sponsored' => true
        ]);

        $html = $this->parser->generateHtml($data);

        $this->assertStringContainsString('rel="sponsored"', $html);
    }

    public function test_generate_html_link_attributes_with_new_tab()
    {
        $data = $this->parser->parse([
            'title' => 'Card',
            'linkUrl' => 'https://example.com',
            'openInNewTab' => true
        ]);

        $html = $this->parser->generateHtml($data);

        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    public function test_generate_html_link_attributes_combined()
    {
        $data = $this->parser->parse([
            'title' => 'Card',
            'linkUrl' => 'https://example.com',
            'openInNewTab' => true,
            'noFollow' => true,
            'sponsored' => true
        ]);

        $html = $this->parser->generateHtml($data);

        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('noopener', $html);
        $this->assertStringContainsString('noreferrer', $html);
        $this->assertStringContainsString('nofollow', $html);
        $this->assertStringContainsString('sponsored', $html);
    }

    public function test_generate_html_button_types()
    {
        $types = ['primary', 'secondary', 'text'];

        foreach ($types as $type) {
            $data = $this->parser->parse([
                'title' => 'Card',
                'linkUrl' => 'https://example.com',
                'buttonType' => $type
            ]);

            $html = $this->parser->generateHtml($data);

            $this->assertStringContainsString("card-button-{$type}", $html,
                "Failed for button type: {$type}");
        }
    }

    public function test_generate_html_context_classes()
    {
        $contexts = ['default', 'featured', 'premium'];

        foreach ($contexts as $context) {
            $data = $this->parser->parse([
                'title' => 'Card',
                'context' => $context
            ]);

            $html = $this->parser->generateHtml($data);

            $this->assertStringContainsString("card-block-{$context}", $html,
                "Failed for context: {$context}");
        }
    }

    public function test_generate_html_without_button_when_no_url()
    {
        $data = $this->parser->parse([
            'title' => 'Card without link',
            'description' => 'Just text'
        ]);

        $html = $this->parser->generateHtml($data);

        $this->assertStringNotContainsString('card-button', $html);
    }

    public function test_parse_trims_whitespace()
    {
        $input = [
            'title' => '  Trimmed Title  ',
            'description' => '  Trimmed Description  '
        ];

        $result = $this->parser->parse($input);

        $this->assertEquals('Trimmed Title', $result['title']);
        $this->assertEquals('Trimmed Description', $result['description']);
    }

    public function test_generate_html_uses_title_as_image_alt_fallback()
    {
        $data = $this->parser->parse([
            'title' => 'Card Title',
            'image' => [
                'src' => 'https://example.com/image.jpg'
                // No alt text provided
            ]
        ]);

        $html = $this->parser->generateHtml($data);

        $this->assertStringContainsString('alt="Card Title"', $html);
    }

    public function test_parse_default_context()
    {
        $result = $this->parser->parse([]);

        $this->assertEquals('default', $result['context']);
    }

    public function test_parse_custom_context()
    {
        $result = $this->parser->parse(['context' => 'featured']);

        $this->assertEquals('featured', $result['context']);
    }

    public function test_parse_default_layout_and_alignment()
    {
        $result = $this->parser->parse([]);

        $this->assertEquals('full', $result['layout']);
        $this->assertEquals('center', $result['alignment']);
    }

    public function test_parse_custom_layout()
    {
        $result = $this->parser->parse(['layout' => 'inline']);
        $this->assertEquals('inline', $result['layout']);

        $result = $this->parser->parse(['layout' => 'extended']);
        $this->assertEquals('extended', $result['layout']);
    }

    public function test_parse_custom_alignment()
    {
        $alignments = ['left', 'center', 'right', 'fullscreen'];

        foreach ($alignments as $alignment) {
            $result = $this->parser->parse(['alignment' => $alignment]);
            $this->assertEquals($alignment, $result['alignment']);
        }
    }

    public function test_generate_html_includes_layout_class()
    {
        $data = $this->parser->parse([
            'title' => 'Test',
            'layout' => 'inline'
        ]);

        $html = $this->parser->generateHtml($data);
        $this->assertStringContainsString('card-layout-inline', $html);
    }

    public function test_generate_html_includes_alignment_class()
    {
        $data = $this->parser->parse([
            'title' => 'Test',
            'alignment' => 'left'
        ]);

        $html = $this->parser->generateHtml($data);
        $this->assertStringContainsString('card-align-left', $html);
    }


    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CardBlockParser();
    }
}
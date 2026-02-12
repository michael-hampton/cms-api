<?php

namespace App\Tests\Unit\Parsers;

use App\Parsers\CardGroupBlockParser;
use PHPUnit\Framework\TestCase;

class CardGroupBlockParserTest extends TestCase
{
    private CardGroupBlockParser $parser;

    public function testGetType(): void
    {
        $this->assertEquals('card-group', $this->parser->getType());
    }

    public function testGetValidationRules(): void
    {
        $rules = $this->parser->getValidationRules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('itemsPerRow', $rules);
        $this->assertArrayHasKey('gap', $rules);
        $this->assertArrayHasKey('cards', $rules);
    }

    public function testParseWithDefaultValues(): void
    {
        $data = [
            'cards' => [],
            'itemsPerRow' => 5,
            'gap' => 'medium',
        ];

        $result = $this->parser->parse($data);

        $this->assertEquals(3, $result['itemsPerRow']);
        $this->assertEquals('medium', $result['gap']);
        $this->assertIsArray($result['cards']);
        $this->assertEmpty($result['cards']);
    }

    public function testParseWithCustomItemsPerRow(): void
    {
        $data = [
            'itemsPerRow' => 2,
            'cards' => [],
            'gap' => 'medium',
        ];

        $result = $this->parser->parse($data);

        $this->assertEquals(2, $result['itemsPerRow']);
    }

    public function testParseItemsPerRowOutOfRange(): void
    {
        $data = [
            'itemsPerRow' => 5,
            'cards' => [],
            'gap' => 'medium',
        ];

        $result = $this->parser->parse($data);
        $this->assertEquals(3, $result['itemsPerRow']); // Should default to 3

        $data['itemsPerRow'] = 0;
        $result = $this->parser->parse($data);
        $this->assertEquals(3, $result['itemsPerRow']); // Should default to 3
    }

    public function testParseWithValidItemsPerRowRange(): void
    {
        foreach ([1, 2, 3, 4] as $count) {
            $data = ['itemsPerRow' => $count, 'cards' => []];
            $result = $this->parser->parse($data);
            $this->assertEquals($count, $result['itemsPerRow']);
        }
    }

    public function testParseWithGapValues(): void
    {
        $gaps = ['small', 'medium', 'large'];

        foreach ($gaps as $gap) {
            $data = [
                'gap' => $gap,
                'cards' => [],
                'itemsPerRow' => 5,
            ];

            $result = $this->parser->parse($data);
            $this->assertEquals($gap, $result['gap']);
        }
    }

    public function testParseSanitizesGap(): void
    {
        $data = [
            'gap' => '  large  ',
            'cards' => [],
            'itemsPerRow' => 5,
        ];

        $result = $this->parser->parse($data);
        $this->assertEquals('medium', $result['gap']);
    }

    public function testParseWithCards(): void
    {
        $data = [
            'itemsPerRow' => 3,
            'gap' => 'medium',
            'cards' => [
                [
                    'title' => 'Card 1',
                    'description' => 'Description 1',
                    'linkUrl' => 'https://example.com'
                ],
                [
                    'title' => 'Card 2',
                    'description' => 'Description 2'
                ]
            ]
        ];

        $result = $this->parser->parse($data);

        $this->assertCount(2, $result['cards']);
        $this->assertEquals('Card 1', $result['cards'][0]['title']);
        $this->assertEquals('Card 2', $result['cards'][1]['title']);
    }

    public function testParseFiltersOutEmptyCards(): void
    {
        $data = [
            'cards' => [
                [
                    'title' => 'Card 1',
                    'description' => 'Description 1'
                ],
                [
                    'title' => '',
                    'description' => '',
                    'image' => ['src' => '']
                ],
                [
                    'title' => 'Card 2'
                ]
            ],
            'itemsPerRow' => 3,
        ];

        $result = $this->parser->parse($data);

        $this->assertCount(2, $result['cards']);
        $this->assertEquals('Card 1', $result['cards'][0]['title']);
        $this->assertEquals('Card 2', $result['cards'][1]['title']);
    }

    public function testParseIncludesCardWithOnlyImage(): void
    {
        $data = [
            'cards' => [
                [
                    'title' => '',
                    'description' => '',
                    'image' => [
                        'src' => 'https://example.com/image.jpg',
                        'alt' => 'Test image'
                    ]
                ]
            ],
            'itemsPerRow' => 3,
        ];

        $result = $this->parser->parse($data);

        $this->assertCount(1, $result['cards']);
        $this->assertEquals('https://example.com/image.jpg', $result['cards'][0]['image']['src']);
    }

    public function testGenerateHtmlWithEmptyCards(): void
    {
        $parsedData = [
            'itemsPerRow' => 3,
            'gap' => 'medium',
            'cards' => []
        ];

        $html = $this->parser->generateHtml($parsedData);

        $this->assertEmpty($html);
    }

    public function testGenerateHtmlWithCards(): void
    {
        $parsedData = [
            'itemsPerRow' => 2,
            'gap' => 'large',
            'cards' => [
                [
                    'title' => 'Test Card',
                    'description' => 'Test Description',
                    'linkUrl' => '',
                    'buttonType' => 'primary',
                    'buttonText' => 'Learn More',
                    'noFollow' => false,
                    'sponsored' => false,
                    'openInNewTab' => false,
                    'image' => null,
                    'endorsement' => null,
                    'sponsorDeclaration' => null,
                    'context' => 'default',
                    'itemsPerRow' => 3
                ]
            ]
        ];

        $html = $this->parser->generateHtml($parsedData);

        $this->assertStringContainsString('card-group-block', $html);
        $this->assertStringContainsString('card-group-items-2', $html);
        $this->assertStringContainsString('card-group-gap-large', $html);
        $this->assertStringContainsString('card-group-container', $html);
        $this->assertStringContainsString('card-group-item', $html);
        $this->assertStringContainsString('Test Card', $html);
    }

    public function testGenerateHtmlWithMultipleCards(): void
    {
        $parsedData = [
            'itemsPerRow' => 3,
            'gap' => 'medium',
            'cards' => [
                [
                    'title' => 'Card 1',
                    'description' => 'Description 1',
                    'linkUrl' => '',
                    'buttonType' => 'primary',
                    'buttonText' => 'Learn More',
                    'noFollow' => false,
                    'sponsored' => false,
                    'openInNewTab' => false,
                    'image' => null,
                    'endorsement' => null,
                    'sponsorDeclaration' => null,
                    'context' => 'default',
                    'itemsPerRow' => 3
                ],
                [
                    'title' => 'Card 2',
                    'description' => 'Description 2',
                    'linkUrl' => '',
                    'buttonType' => 'primary',
                    'buttonText' => 'Learn More',
                    'noFollow' => false,
                    'sponsored' => false,
                    'openInNewTab' => false,
                    'image' => null,
                    'endorsement' => null,
                    'sponsorDeclaration' => null,
                    'context' => 'default',
                    'itemsPerRow' => 3
                ]
            ]
        ];

        $html = $this->parser->generateHtml($parsedData);

        $this->assertStringContainsString('Card 1', $html);
        $this->assertStringContainsString('Card 2', $html);

        // Count occurrences of card-group-item
        $this->assertEquals(3, substr_count($html, 'card-group-item'));
    }

    public function testGenerateHtmlAppliesCorrectContainerClasses(): void
    {
        $configurations = [
            ['itemsPerRow' => 1, 'gap' => 'small'],
            ['itemsPerRow' => 2, 'gap' => 'medium'],
            ['itemsPerRow' => 3, 'gap' => 'large'],
            ['itemsPerRow' => 4, 'gap' => 'medium']
        ];

        foreach ($configurations as $config) {
            $parsedData = [
                'itemsPerRow' => $config['itemsPerRow'],
                'gap' => $config['gap'],
                'cards' => [
                    [
                        'title' => 'Test',
                        'description' => '',
                        'linkUrl' => '',
                        'buttonType' => 'primary',
                        'buttonText' => 'Learn More',
                        'noFollow' => false,
                        'sponsored' => false,
                        'openInNewTab' => false,
                        'image' => null,
                        'endorsement' => null,
                        'sponsorDeclaration' => null,
                        'context' => 'default',
                        'itemsPerRow' => 3
                    ]
                ]
            ];

            $html = $this->parser->generateHtml($parsedData);

            $this->assertStringContainsString("card-group-items-{$config['itemsPerRow']}", $html);
            $this->assertStringContainsString("card-group-gap-{$config['gap']}", $html);
        }
    }

    public function testParseHandlesNullCards(): void
    {
        $data = [
            'itemsPerRow' => 3,
            'gap' => 'medium'
        ];

        $result = $this->parser->parse($data);

        $this->assertIsArray($result['cards']);
        $this->assertEmpty($result['cards']);
    }

    public function testParseHandlesInvalidCardsType(): void
    {
        $data = [
            'cards' => 'invalid'
        ];

        $result = $this->parser->parse($data);

        $this->assertIsArray($result['cards']);
        $this->assertEmpty($result['cards']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CardGroupBlockParser();
    }
}
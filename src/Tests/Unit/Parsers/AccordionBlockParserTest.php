<?php

namespace App\Tests\Unit\Parsers;

use App\Parsers\AccordionBlockParser;
use PHPUnit\Framework\TestCase;

class AccordionBlockParserTest extends TestCase
{
    private AccordionBlockParser $parser;

    public function testGetType(): void
    {
        $this->assertEquals('accordion', $this->parser->getType());
    }

    public function testParseWithBasicData(): void
    {
        $data = [
            'title' => 'FAQ',
            'items' => [
                ['question' => 'Q1?', 'answer' => 'A1', 'isOpen' => false],
                ['question' => 'Q2?', 'answer' => 'A2', 'isOpen' => false]
            ],
            'allowMultipleOpen' => false,
            'theme' => 'light',
            'visibleItemsCount' => 5
        ];

        $result = $this->parser->parse($data);

        $this->assertEquals('FAQ', $result['title']);
        $this->assertCount(2, $result['items']);
        $this->assertEquals('light', $result['theme']);
        $this->assertEquals(5, $result['visibleItemsCount']);
        $this->assertEquals(2, $result['total_items']);
    }

    public function testParseWithIntroContent(): void
    {
        $data = [
            'title' => 'Help Center',
            'introContent' => 'Welcome to our help center. Find answers below.',
            'items' => [
                ['question' => 'Q1?', 'answer' => 'A1']
            ],
            'theme' => 'colored',
            'visibleItemsCount' => 10,
            'allowMultipleOpen' => true,
        ];

        $result = $this->parser->parse($data);

        $this->assertEquals('Welcome to our help center. Find answers below.', $result['introContent']);
    }

    public function testParseWithCustomVisibleItemsCount(): void
    {
        $data = [
            'items' => [
                ['question' => 'Q1?', 'answer' => 'A1'],
                ['question' => 'Q2?', 'answer' => 'A2'],
                ['question' => 'Q3?', 'answer' => 'A3']
            ],
            'theme' => 'dark',
            'visibleItemsCount' => 2,
            'allowMultipleOpen' => true,
            'title' => 'Test'
        ];

        $result = $this->parser->parse($data);

        $this->assertEquals(2, $result['visibleItemsCount']);
    }

    public function testParseClampVisibleItemsCountToMinimum(): void
    {
        $data = [
            'items' => [['question' => 'Q?', 'answer' => 'A']],
            'theme' => 'light',
            'visibleItemsCount' => 0,
            'allowMultipleOpen' => true,
            'title' => 'Test'
        ];

        $result = $this->parser->parse($data);

        $this->assertEquals(1, $result['visibleItemsCount']);
    }

    public function testParseClampVisibleItemsCountToMaximum(): void
    {
        $data = [
            'items' => [['question' => 'Q?', 'answer' => 'A']],
            'theme' => 'light',
            'visibleItemsCount' => 100,
            'allowMultipleOpen' => true,
            'title' => 'Test'
        ];

        $result = $this->parser->parse($data);

        $this->assertEquals(50, $result['visibleItemsCount']);
    }

    public function testParseWithAllThemes(): void
    {
        $themes = ['light', 'dark', 'colored', 'minimal'];

        foreach ($themes as $theme) {
            $data = [
                'items' => [['question' => 'Q?', 'answer' => 'A']],
                'theme' => $theme,
                'visibleItemsCount' => 5,
                'allowMultipleOpen' => true,
                'title' => 'Test'
            ];

            $result = $this->parser->parse($data);

            $this->assertEquals($theme, $result['theme']);
        }
    }

    public function testParseWithInvalidTheme(): void
    {
        $data = [
            'items' => [['question' => 'Q?', 'answer' => 'A']],
            'theme' => 'invalid-theme',
            'visibleItemsCount' => 5,
            'allowMultipleOpen' => true,
            'title' => 'Test'
        ];

        $result = $this->parser->parse($data);

        $this->assertEquals('light', $result['theme']); // Falls back to default
    }

    public function testParseItemsWithOrder(): void
    {
        $data = [
            'items' => [
                ['question' => 'Third', 'answer' => 'A3', 'order' => 2],
                ['question' => 'First', 'answer' => 'A1', 'order' => 0],
                ['question' => 'Second', 'answer' => 'A2', 'order' => 1]
            ],
            'theme' => 'light',
            'visibleItemsCount' => 5,
            'allowMultipleOpen' => true,
            'title' => 'Test'
        ];

        $result = $this->parser->parse($data);

        $this->assertEquals('First', $result['items'][0]['question']);
        $this->assertEquals('Second', $result['items'][1]['question']);
        $this->assertEquals('Third', $result['items'][2]['question']);
        $this->assertEquals(0, $result['items'][0]['order']);
        $this->assertEquals(1, $result['items'][1]['order']);
        $this->assertEquals(2, $result['items'][2]['order']);
    }

    public function testParseReindexesOrdersAfterSorting(): void
    {
        $data = [
            'items' => [
                ['question' => 'Q1', 'answer' => 'A1', 'order' => 10],
                ['question' => 'Q2', 'answer' => 'A2', 'order' => 5],
                ['question' => 'Q3', 'answer' => 'A3', 'order' => 15]
            ],
            'theme' => 'light',
            'visibleItemsCount' => 5,
            'allowMultipleOpen' => true,
            'title' => 'Test'
        ];

        $result = $this->parser->parse($data);

        $this->assertEquals(0, $result['items'][0]['order']);
        $this->assertEquals(1, $result['items'][1]['order']);
        $this->assertEquals(2, $result['items'][2]['order']);
    }

    public function testParseSkipsInvalidItems(): void
    {
        $data = [
            'items' => [
                ['question' => 'Valid Q', 'answer' => 'Valid A'],
                ['question' => '', 'answer' => 'No question'],
                ['question' => 'No answer', 'answer' => ''],
                ['question' => 'Another valid', 'answer' => 'Valid answer']
            ],
            'theme' => 'light',
            'visibleItemsCount' => 5,
            'allowMultipleOpen' => true,
            'title' => 'Test'
        ];

        $result = $this->parser->parse($data);

        $this->assertCount(2, $result['items']);
        $this->assertEquals(2, $result['total_items']);
    }

    public function testParseTrimsWhitespace(): void
    {
        $data = [
            'title' => '  FAQ  ',
            'introContent' => '  Welcome  ',
            'items' => [
                ['question' => '  Question?  ', 'answer' => '  Answer.  ']
            ],
            'theme' => 'light',
            'visibleItemsCount' => 5,
            'allowMultipleOpen' => true,
        ];

        $result = $this->parser->parse($data);

        $this->assertEquals('FAQ', $result['title']);
        $this->assertEquals('Welcome', $result['introContent']);
        $this->assertEquals('Question?', $result['items'][0]['question']);
        $this->assertEquals('Answer.', $result['items'][0]['answer']);
    }

    public function testParseWithOpenFirstByDefault(): void
    {
        $data = [
            'items' => [
                ['question' => 'Q1?', 'answer' => 'A1'],
                ['question' => 'Q2?', 'answer' => 'A2']
            ],
            'openFirstByDefault' => true,
            'theme' => 'light',
            'visibleItemsCount' => 5,
            'allowMultipleOpen' => true,
            'title' => 'Test'
        ];

        $result = $this->parser->parse($data);

        $this->assertTrue($result['items'][0]['isOpen']);
        $this->assertFalse($result['items'][1]['isOpen']);
    }

    public function testParseWithOpenFirstByDefaultFalse(): void
    {
        $data = [
            'items' => [
                ['question' => 'Q1?', 'answer' => 'A1', 'isOpen' => false],
                ['question' => 'Q2?', 'answer' => 'A2', 'isOpen' => false]
            ],
            'openFirstByDefault' => false,
            'theme' => 'light',
            'visibleItemsCount' => 5,
            'allowMultipleOpen' => true,
            'title' => 'Test'
        ];

        $result = $this->parser->parse($data);

        $this->assertFalse($result['items'][0]['isOpen']);
        $this->assertFalse($result['items'][1]['isOpen']);
    }

    public function testParseWithAllowMultipleOpen(): void
    {
        $data = [
            'items' => [['question' => 'Q?', 'answer' => 'A']],
            'allowMultipleOpen' => true,
            'theme' => 'light',
            'visibleItemsCount' => 5,
            'title' => 'Test'
        ];

        $result = $this->parser->parse($data);

        $this->assertTrue($result['allowMultipleOpen']);
    }

    public function testParseWithSidebarContext(): void
    {
        $data = [
            'items' => [['question' => 'Q?', 'answer' => 'A']],
            'context' => 'sidebar',
            'theme' => 'minimal',
            'visibleItemsCount' => 3,
            'allowMultipleOpen' => true,
            'title' => 'Test'
        ];

        $result = $this->parser->parse($data);

        $this->assertEquals('sidebar', $result['context']);
    }

    public function testParseWithInvalidContext(): void
    {
        $data = [
            'items' => [['question' => 'Q?', 'answer' => 'A']],
            'context' => 'invalid',
            'theme' => 'light',
            'visibleItemsCount' => 5,
            'allowMultipleOpen' => true,
            'title' => 'Test'
        ];

        $result = $this->parser->parse($data);

        $this->assertEquals('default', $result['context']);
    }

    public function testGenerateHtmlDefault(): void
    {
        $parsedData = [
            'title' => 'FAQ',
            'introContent' => 'Find answers below',
            'items' => [
                ['question' => 'Q1?', 'answer' => 'A1', 'isOpen' => true, 'order' => 0],
                ['question' => 'Q2?', 'answer' => 'A2', 'isOpen' => false, 'order' => 1]
            ],
            'allowMultipleOpen' => false,
            'context' => 'default',
            'theme' => 'light',
            'visibleItemsCount' => 5,
            'total_items' => 2
        ];

        $html = $this->parser->generateHtml($parsedData);

        $this->assertStringContainsString('accordion-block', $html);
        $this->assertStringContainsString('accordion-theme-light', $html);
        $this->assertStringContainsString('FAQ', $html);
        $this->assertStringContainsString('Find answers below', $html);
        $this->assertStringContainsString('Q1?', $html);
        $this->assertStringContainsString('Q2?', $html);
        $this->assertStringContainsString('data-allow-multiple="false"', $html);
        $this->assertStringContainsString('<script>', $html);
    }

    public function testGenerateHtmlSidebar(): void
    {
        $parsedData = [
            'title' => 'Quick Help',
            'items' => [
                ['question' => 'Q?', 'answer' => 'A', 'isOpen' => false, 'order' => 0]
            ],
            'allowMultipleOpen' => true,
            'context' => 'sidebar',
            'theme' => 'dark',
            'visibleItemsCount' => 5,
            'total_items' => 1
        ];

        $html = $this->parser->generateHtml($parsedData);

        $this->assertStringContainsString('accordion-sidebar', $html);
        $this->assertStringContainsString('accordion-theme-dark', $html);
        $this->assertStringContainsString('Quick Help', $html);
        $this->assertStringContainsString('data-allow-multiple="true"', $html);
    }

    public function testGenerateHtmlWithLoadMoreButton(): void
    {
        $items = [];
        for ($i = 1; $i <= 10; $i++) {
            $items[] = ['question' => "Q{$i}?", 'answer' => "A{$i}", 'isOpen' => false, 'order' => $i - 1];
        }

        $parsedData = [
            'title' => 'Many Questions',
            'items' => $items,
            'allowMultipleOpen' => false,
            'context' => 'default',
            'theme' => 'colored',
            'visibleItemsCount' => 5,
            'total_items' => 10
        ];

        $html = $this->parser->generateHtml($parsedData);

        $this->assertStringContainsString('accordion-load-more-btn', $html);
        $this->assertStringContainsString('Load More Questions', $html);
        $this->assertStringContainsString('accordion-item-hidden', $html);
        $this->assertStringContainsString('data-visible-count="5"', $html);
    }

    public function testGenerateHtmlWithNoLoadMoreButton(): void
    {
        $parsedData = [
            'items' => [
                ['question' => 'Q1?', 'answer' => 'A1', 'isOpen' => false, 'order' => 0],
                ['question' => 'Q2?', 'answer' => 'A2', 'isOpen' => false, 'order' => 1]
            ],
            'allowMultipleOpen' => false,
            'context' => 'default',
            'theme' => 'light',
            'visibleItemsCount' => 5,
            'total_items' => 2,
            'title' => 'Test'
        ];

        $html = $this->parser->generateHtml($parsedData);

        // Should NOT contain load more button since we only have 2 items and visible count is 5
        $this->assertStringNotContainsString('accordion-load-more-container', $html);
    }

    public function testGenerateHtmlEscapesUserInput(): void
    {
        $parsedData = [
            'title' => '<script>alert("xss")</script>',
            'items' => [
                ['question' => '<b>Bold?</b>', 'answer' => '<i>Italic</i>', 'isOpen' => false, 'order' => 0]
            ],
            'allowMultipleOpen' => false,
            'context' => 'default',
            'theme' => 'light',
            'visibleItemsCount' => 5,
            'total_items' => 1
        ];

        $html = $this->parser->generateHtml($parsedData);

        // Should not contain unescaped script tags
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;Bold?&lt;/b&gt;', $html);
        $this->assertStringContainsString('&lt;i&gt;Italic&lt;/i&gt;', $html);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new AccordionBlockParser();
    }
}
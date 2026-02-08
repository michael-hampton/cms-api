<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Models\Newsletter;
use App\Services\Newsletter\NewsletterContentBuilder;
use App\Services\Newsletter\NewsletterPageBuilderService;
use Mockery;
use PHPUnit\Framework\TestCase;

class NewsletterContentBuilderTest extends TestCase
{
    private NewsletterContentBuilder $builder;
    private $mockPageBuilderService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockPageBuilderService = Mockery::mock(NewsletterPageBuilderService::class);
        $this->builder = new NewsletterContentBuilder($this->mockPageBuilderService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testBuildAutomatedNewsletterSuccessfully()
    {
        $newsletter = $this->createMockNewsletter(true);
        $siteId = 1;

        $mockPages = collect([
            (object)[
                'id' => 1,
                'title' => 'Page 1',
                'subtitle' => 'Subtitle 1',
                'slug' => 'page-1'
            ],
            (object)[
                'id' => 2,
                'title' => 'Page 2',
                'subtitle' => 'Subtitle 2',
                'slug' => 'page-2'
            ]
        ]);

        $this->mockPageBuilderService->shouldReceive('getPagesForNewsletter')
            ->once()
            ->with($newsletter, $siteId)
            ->andReturn($mockPages);

        $this->mockPageBuilderService->shouldReceive('buildNewsletterHtml')
            ->once()
            ->with($newsletter, $mockPages, null, null, false, null, 1)
            ->andReturn('<p>Automated content</p>{{UNSUBSCRIBE_LINK}}');

        $result = $this->builder->build($newsletter, $siteId, false);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Automated content', $result['html']);
        $this->assertCount(2, $result['pages']);
        $this->assertEquals('Page 1', $result['pages'][0]['title']);
    }

    public function testBuildAutomatedNewsletterFailsWithNoPages()
    {
        $newsletter = $this->createMockNewsletter(true);
        $siteId = 1;

        $this->mockPageBuilderService->shouldReceive('getPagesForNewsletter')
            ->once()
            ->andReturn(collect([]));

        $result = $this->builder->build($newsletter, $siteId, false);

        $this->assertFalse($result['success']);
        $this->assertEquals('No pages match newsletter criteria', $result['error']);
        $this->assertEquals($newsletter->id, $result['newsletter_id']);
    }

    public function testBuildManualNewsletterWithParagraph()
    {
        $newsletter = $this->createMockNewsletter(false);
        $newsletter->content = json_encode([
            ['type' => 'paragraph', 'content' => 'Hello world']
        ]);

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('<p>Hello world</p>', $result['html']);
        $this->assertStringContainsString('{{UNSUBSCRIBE_LINK}}', $result['html']);
        $this->assertEmpty($result['pages']);
    }

    public function testBuildManualNewsletterWithHeading()
    {
        $newsletter = $this->createMockNewsletter(false);
        $newsletter->content = json_encode([
            ['type' => 'heading', 'level' => 2, 'content' => 'Main Title']
        ]);

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('<h2>Main Title</h2>', $result['html']);
    }

    public function testBuildManualNewsletterWithImage()
    {
        $newsletter = $this->createMockNewsletter(false);
        $newsletter->content = json_encode([
            [
                'type' => 'image',
                'url' => 'https://example.com/image.jpg',
                'alt' => 'Test image'
            ]
        ]);

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('src="https://example.com/image.jpg"', $result['html']);
        $this->assertStringContainsString('alt="Test image"', $result['html']);
    }

    public function testBuildManualNewsletterWithList()
    {
        $newsletter = $this->createMockNewsletter(false);
        $newsletter->content = json_encode([
            [
                'type' => 'list',
                'items' => ['Item 1', 'Item 2', 'Item 3']
            ]
        ]);

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('<ul>', $result['html']);
        $this->assertStringContainsString('<li>Item 1</li>', $result['html']);
        $this->assertStringContainsString('<li>Item 2</li>', $result['html']);
        $this->assertStringContainsString('<li>Item 3</li>', $result['html']);
        $this->assertStringContainsString('</ul>', $result['html']);
    }

    public function testBuildManualNewsletterWithButton()
    {
        $newsletter = $this->createMockNewsletter(false);
        $newsletter->content = json_encode([
            [
                'type' => 'button',
                'url' => 'https://example.com',
                'content' => 'Click me'
            ]
        ]);

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('href="https://example.com"', $result['html']);
        $this->assertStringContainsString('Click me', $result['html']);
        $this->assertStringContainsString('background-color: #007bff', $result['html']);
    }

    public function testBuildManualNewsletterWithMultipleBlocks()
    {
        $newsletter = $this->createMockNewsletter(false);
        $newsletter->content = json_encode([
            ['type' => 'heading', 'level' => 1, 'content' => 'Newsletter Title'],
            ['type' => 'paragraph', 'content' => 'Introduction text'],
            ['type' => 'image', 'url' => 'image.jpg', 'alt' => 'Photo'],
            ['type' => 'list', 'items' => ['Point 1', 'Point 2']],
            ['type' => 'button', 'url' => '#', 'content' => 'Read more']
        ]);

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('<h1>Newsletter Title</h1>', $result['html']);
        $this->assertStringContainsString('<p>Introduction text</p>', $result['html']);
        $this->assertStringContainsString('src="image.jpg"', $result['html']);
        $this->assertStringContainsString('<li>Point 1</li>', $result['html']);
        $this->assertStringContainsString('Read more', $result['html']);
    }

    public function testBuildHandlesInvalidJson()
    {
        $newsletter = $this->createMockNewsletter(false);
        $newsletter->content = '{invalid json';

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertTrue($result['success']);
        $this->assertEquals("\n{{UNSUBSCRIBE_LINK}}", $result['html']);
    }

    public function testBuildHandlesUnknownBlockType()
    {
        $newsletter = $this->createMockNewsletter(false);
        $newsletter->content = json_encode([
            ['type' => 'unknown', 'content' => 'Some content']
        ]);

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('<div>Some content</div>', $result['html']);
    }

    public function testBuildEscapesHtmlInContent()
    {
        $newsletter = $this->createMockNewsletter(false);
        $newsletter->content = json_encode([
            ['type' => 'paragraph', 'content' => '<script>alert("xss")</script>']
        ]);

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertTrue($result['success']);
        $this->assertStringNotContainsString('<script>', $result['html']);
        $this->assertStringContainsString('&lt;script&gt;', $result['html']);
    }

    public function testBuildAddsUnsubscribePlaceholderIfMissing()
    {
        $newsletter = $this->createMockNewsletter(false);
        $newsletter->content = json_encode([
            ['type' => 'paragraph', 'content' => 'Content without placeholder']
        ]);

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('{{UNSUBSCRIBE_LINK}}', $result['html']);
    }

    public function testBuildPreservesExistingUnsubscribePlaceholder()
    {
        $newsletter = $this->createMockNewsletter(true);
        $siteId = 1;

        $mockPages = collect([
            (object)['id' => 1, 'title' => 'Page', 'subtitle' => '', 'slug' => 'page']
        ]);

        $this->mockPageBuilderService->shouldReceive('getPagesForNewsletter')
            ->andReturn($mockPages);

        $this->mockPageBuilderService->shouldReceive('buildNewsletterHtml')
            ->andReturn('<p>Content</p>{{UNSUBSCRIBE_LINK}}<p>More</p>');

        $result = $this->builder->build($newsletter, $siteId, false);

        $this->assertTrue($result['success']);
        // Should not add another placeholder
        $this->assertEquals(1, substr_count($result['html'], '{{UNSUBSCRIBE_LINK}}'));
    }

    public function testBuildWithPreviewMode()
    {
        $newsletter = $this->createMockNewsletter(true);
        $siteId = 1;

        $mockPages = collect([
            (object)['id' => 1, 'title' => 'Page', 'subtitle' => '', 'slug' => 'page']
        ]);

        $this->mockPageBuilderService->shouldReceive('getPagesForNewsletter')
            ->with($newsletter, $siteId)
            ->andReturn($mockPages);

        $this->mockPageBuilderService->shouldReceive('buildNewsletterHtml')
            ->with($newsletter, $mockPages, null, null, true, null, 1)
            ->andReturn('<p>Preview content</p>');

        $result = $this->builder->build($newsletter, $siteId, true);

        $this->assertTrue($result['success']);
    }

    private function createMockNewsletter(bool $isAutomated): Newsletter
    {
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->id = 1;
        $newsletter->content = '[]';
        $newsletter->shouldReceive('isAutomated')->andReturn($isAutomated);
        return $newsletter;
    }
}
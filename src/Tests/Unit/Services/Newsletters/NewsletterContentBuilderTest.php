<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Models\Newsletter;
use App\Repositories\Newsletters\NewsletterBrandingRepository;
use App\Repositories\Newsletters\NewsletterLayoutRepository;
use App\Services\Newsletter\NewsletterContentBuilder;
use App\Services\Newsletter\NewsletterContentResolver;
use App\Services\Newsletter\NewsletterPageBuilderService;
use Mockery;
use PHPUnit\Framework\TestCase;

class NewsletterContentBuilderTest extends TestCase
{
    private NewsletterContentBuilder $builder;
    private $mockPageBuilderService;
    private NewsletterBrandingRepository $newsletterBrandingRepository;
    private readonly NewsletterLayoutRepository $newsletterLayoutRepository;
    private NewsletterContentResolver $newsletterContentResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pageBuilderService = Mockery::mock(NewsletterPageBuilderService::class);
        $this->brandingRepository = Mockery::mock(NewsletterBrandingRepository::class);
        $this->layoutRepository = Mockery::mock(NewsletterLayoutRepository::class);
        $this->contentResolver = Mockery::mock(NewsletterContentResolver::class);

        // Default safe stubs
        $this->brandingRepository
            ->shouldReceive('findByNewsletterId')
            ->zeroOrMoreTimes()
            ->andReturn(null);

        $this->layoutRepository
            ->shouldReceive('versionHistory')
            ->zeroOrMoreTimes()
            ->andReturn(collect());

        $this->builder = new NewsletterContentBuilder(
            $this->pageBuilderService,
            $this->brandingRepository,
            $this->layoutRepository,
            $this->contentResolver
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createMockNewsletter(bool $isAutomated): Newsletter
    {
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->id = 1;
        $newsletter->layout_id = null;
        $newsletter->content = '[]';

        $newsletter->shouldReceive('isAutomated')->andReturn($isAutomated);

        return $newsletter;
    }

    private function stubResolver(string $html = '<p>Resolved Content</p>{{UNSUBSCRIBE_LINK}}'): void
    {
        $this->contentResolver
            ->shouldReceive('resolve')
            ->zeroOrMoreTimes()
            ->andReturn($html);
    }

    private function stubPages(array $pages = []): void
    {
        $this->pageBuilderService
            ->shouldReceive('getPagesForNewsletter')
            ->zeroOrMoreTimes()
            ->andReturn(collect($pages));
    }

    public function testBuildAutomatedNewsletterSuccessfully()
    {
        $newsletter = $this->createMockNewsletter(true);
        $siteId = 1;

        $this->stubPages([
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

        $this->stubResolver('<p>Automated content</p>{{UNSUBSCRIBE_LINK}}');

        $result = $this->builder->build($newsletter, $siteId, false);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Automated content', $result['html']);
        $this->assertCount(2, $result['pages']);
    }

    public function testBuildAutomatedNewsletterFailsWithNoPages()
    {
        $newsletter = $this->createMockNewsletter(true);

        $this->pageBuilderService
            ->shouldReceive('getPagesForNewsletter')
            ->once()
            ->andReturn(collect());

        $this->stubResolver('<p>Content</p>');

        $result = $this->builder->build($newsletter, 1, false);

        // Builder does NOT fail hard anymore
        $this->assertTrue($result['success']);

        $this->assertEquals(
            'No pages match newsletter criteria',
            $result['pages']['error']
        );
    }

    public function testBuildManualNewsletterWithParagraph()
    {
        $newsletter = $this->createMockNewsletter(false);

        $newsletter->content = json_encode([
            ['type' => 'paragraph', 'content' => 'Hello world']
        ]);

        $this->stubPages();
        $this->stubResolver('<p>Hello world</p>{{UNSUBSCRIBE_LINK}}');

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('<p>Hello world</p>', $result['html']);
    }

    public function testBuildManualNewsletterWithHeading()
    {
        $newsletter = $this->createMockNewsletter(false);

        $newsletter->content = json_encode([
            ['type' => 'heading', 'level' => 2, 'content' => 'Main Title']
        ]);

        $this->stubPages();
        $this->stubResolver('<h2>Main Title</h2>');

        $result = $this->builder->build($newsletter, 1, false);

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

        $this->stubPages();
        $this->stubResolver('<img src="https://example.com/image.jpg" alt="Test image">');

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertStringContainsString('src="https://example.com/image.jpg"', $result['html']);
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

        $this->stubPages();
        $this->stubResolver('<ul><li>Item 1</li><li>Item 2</li><li>Item 3</li></ul>');

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertStringContainsString('<ul>', $result['html']);
        $this->assertStringContainsString('<li>Item 1</li>', $result['html']);
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

        $this->stubPages();
        $this->stubResolver(
            '<a href="https://example.com">Click me</a>'
        );

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertStringContainsString('href="https://example.com"', $result['html']);
        $this->assertStringContainsString('Click me', $result['html']);
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

        $this->stubPages();

        $this->stubResolver(
            '<h1>Newsletter Title</h1>
        <p>Introduction text</p>
        <img src="image.jpg" alt="Photo">
        <ul><li>Point 1</li><li>Point 2</li></ul>
        <a href="#">Read more</a>'
        );

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Newsletter Title', $result['html']);
        $this->assertStringContainsString('Introduction text', $result['html']);
        $this->assertStringContainsString('Read more', $result['html']);
    }

    public function testBuildHandlesInvalidJson()
    {
        $newsletter = $this->createMockNewsletter(false);
        $newsletter->content = '{invalid json';

        $this->stubPages();
        $this->stubResolver('<p>{{UNSUBSCRIBE_LINK}}</p>');

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertTrue($result['success']);
    }

    public function testBuildHandlesUnknownBlockType()
    {
        $newsletter = $this->createMockNewsletter(false);

        $newsletter->content = json_encode([
            ['type' => 'unknown', 'content' => 'Some content']
        ]);

        $this->stubPages();
        $this->stubResolver('<div>Some content</div>');

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertStringContainsString('Some content', $result['html']);
    }


    public function testBuildEscapesHtmlInContent()
    {
        $newsletter = $this->createMockNewsletter(false);

        $newsletter->content = json_encode([
            ['type' => 'paragraph', 'content' => '<script>alert("xss")</script>']
        ]);

        $this->stubPages();
        $this->stubResolver('<p>&lt;script&gt;alert("xss")&lt;/script&gt;</p>');

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertStringContainsString('&lt;script&gt;', $result['html']);
    }

    public function testBuildAddsUnsubscribePlaceholderIfMissing()
    {
        $newsletter = $this->createMockNewsletter(false);

        $newsletter->content = json_encode([
            ['type' => 'paragraph', 'content' => 'Content without placeholder']
        ]);

        $this->stubPages();
        $this->stubResolver('<p>Content without placeholder</p>');

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertStringContainsString('{{UNSUBSCRIBE_LINK}}', $result['html']);
    }

    public function testBuildPreservesExistingUnsubscribePlaceholder()
    {
        $newsletter = $this->createMockNewsletter(true);

        $this->stubPages([
            (object)['id' => 1, 'title' => 'Page', 'subtitle' => '', 'slug' => 'page']
        ]);

        $this->stubResolver('Content{{UNSUBSCRIBE_LINK}}More');

        $result = $this->builder->build($newsletter, 1, false);

        $this->assertEquals(1, substr_count($result['html'], '{{UNSUBSCRIBE_LINK}}'));
    }


    public function testBuildWithPreviewMode()
    {
        $newsletter = $this->createMockNewsletter(true);

        $this->stubPages([
            (object)['id' => 1, 'title' => 'Page', 'subtitle' => '', 'slug' => 'page']
        ]);

        $this->stubResolver('<p>Preview content</p>');

        $result = $this->builder->build($newsletter, 1, true);

        $this->assertTrue($result['success']);
    }
}
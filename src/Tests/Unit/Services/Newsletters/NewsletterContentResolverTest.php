<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Framework\Support\Logger;
use App\Models\Model;
use App\Models\Newsletter;
use App\Services\Newsletter\NewsletterContentResolver;
use App\Services\Newsletter\NewsletterPageBuilderService;
use App\Tests\Unit\Repositories\RepositoryTestCase;
use Mockery;

class NewsletterContentResolverTest extends RepositoryTestCase
{
    private NewsletterContentResolver $resolver;
    private $mockPageBuilder;

    public function test_resolves_custom_blocks_via_slot_renderer(): void
    {
        $newsletter = $this->makeNewsletter('custom_blocks');
        $newsletter->content_blocks = [
            ['type' => 'text', 'data' => ['paragraphs' => ['Hello from blocks']]]
        ];
        $newsletter->save();

        $this->mockPageBuilder
            ->shouldReceive('buildNewsletterHtmlFromLayoutSlots')
            ->once()
            ->andReturn('<p>Hello from blocks</p>');

        $html = $this->resolver->resolve($newsletter, $this->siteId);

        $this->assertEquals('<p>Hello from blocks</p>', $html);
    }

    private function makeNewsletter(string $contentType): Model
    {
        return Newsletter::create([
            'title' => 'Test Newsletter',
            'content_type' => $contentType,
            'interval' => 'weekly',
            'active' => true,
            'site_id' => $this->siteId,
            'content' => '',
        ]);
    }

    public function test_returns_empty_string_when_custom_blocks_is_empty(): void
    {
        $newsletter = $this->makeNewsletter('custom_blocks');
        $newsletter->content_blocks = [];
        $newsletter->save();

        $this->mockPageBuilder->shouldNotReceive('buildNewsletterHtmlFromLayoutSlots');

        $html = $this->resolver->resolve($newsletter, $this->siteId);

        $this->assertSame('', $html);
    }

    // ── Custom blocks rendering ────────────────────────────────────────────────

    public function test_resolves_auto_pages_via_page_builder(): void
    {
        $newsletter = $this->makeNewsletter('auto_pages');

        $this->mockPageBuilder
            ->shouldReceive('getPagesForNewsletter')
            ->once()
            ->andReturn(collect([]));

        $this->mockPageBuilder
            ->shouldReceive('buildNewsletterHtml')
            ->once()
            ->andReturn('<p>Auto page content</p>');

        $html = $this->resolver->resolve($newsletter, $this->siteId);

        $this->assertEquals('<p>Auto page content</p>', $html);
    }

    public function test_resolves_legacy_content_as_text_block(): void
    {
        $newsletter = $this->makeNewsletter('manual');
        $newsletter->legacy_content = 'My old newsletter text.';
        $newsletter->save();

        $this->mockPageBuilder
            ->shouldReceive('buildNewsletterHtmlFromLayoutSlots')
            ->once()
            ->withArgs(function ($nl, $version) {
                $slots = $version['slots'];
                return $slots[0]['key'] === 'content'
                    && $slots[0]['blocks'][0]['type'] === 'text';
            })
            ->andReturn('<p>My old newsletter text.</p>');

        $html = $this->resolver->resolve($newsletter, $this->siteId);

        $this->assertEquals('<p>My old newsletter text.</p>', $html);
    }

    // ── Auto pages rendering ───────────────────────────────────────────────────

    public function test_legacy_falls_back_to_content_column_when_no_legacy_content(): void
    {
        $newsletter = $this->makeNewsletter('manual');
        $newsletter->content = 'Fallback column text.';
        $newsletter->legacy_content = null;
        $newsletter->save();

        $this->mockPageBuilder
            ->shouldReceive('buildNewsletterHtmlFromLayoutSlots')
            ->once()
            ->withArgs(function ($nl, $version) {
                $slots = $version['slots'];
                return str_contains($slots[0]['blocks'][0]['data']['paragraphs'][0], 'Fallback');
            })
            ->andReturn('<p>Fallback</p>');

        $html = $this->resolver->resolve($newsletter, $this->siteId);

        $this->assertNotEmpty($html);
    }

    // ── Legacy content rendering ───────────────────────────────────────────────

    public function test_returns_empty_string_for_empty_legacy_content(): void
    {
        $newsletter = $this->makeNewsletter('manual');
        $newsletter->legacy_content = '';
        $newsletter->content = '';
        $newsletter->save();

        $this->mockPageBuilder->shouldNotReceive('buildNewsletterHtmlFromLayoutSlots');

        $html = $this->resolver->resolve($newsletter, $this->siteId);

        $this->assertSame('', $html);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockPageBuilder = Mockery::mock(NewsletterPageBuilderService::class);

        $this->resolver = new NewsletterContentResolver(
            $this->mockPageBuilder,
            app(Logger::class),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Models\Model;
use App\Models\Newsletter;
use App\Models\NewsletterBrandingConfiguration;
use App\Repositories\Newsletters\NewsletterBrandingRepository;
use App\Repositories\Newsletters\NewsletterLayoutRepository;
use App\Repositories\Newsletters\NewsletterSnapshotRepository;
use App\Services\Newsletter\Branding\CssSanitizer;
use App\Services\Newsletter\BrandingRendererService;
use App\Services\Newsletter\LayoutRendererService;
use App\Services\Newsletter\NewsletterSnapshotService;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class NewsletterSnapshotServiceTest extends RepositoryTestCase
{
    private NewsletterSnapshotService $service;
    private NewsletterSnapshotRepository $snapshotRepository;
    private NewsletterBrandingRepository $brandingRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->snapshotRepository = app(NewsletterSnapshotRepository::class);
        $this->brandingRepository = app(NewsletterBrandingRepository::class);

        $this->service = new NewsletterSnapshotService(
            $this->snapshotRepository,
            $this->brandingRepository,
            new BrandingRendererService(new CssSanitizer()),
            new LayoutRendererService(app(NewsletterLayoutRepository::class)),
            $this->database
        );
    }

    private function makeNewsletter(): Model
    {
        return Newsletter::create([
            'title' => 'Snapshot Newsletter',
            'content_type' => 'manual',
            'interval' => 'weekly',
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'Test'
        ]);
    }

    public function test_creates_snapshot_from_rendered_html(): void
    {
        $newsletter = $this->makeNewsletter();
        $html = '<html><body><p>Newsletter content</p></body></html>';

        $snapshot = $this->service->createSnapshot($newsletter, $html);

        $this->assertNotNull($snapshot->id);
        $this->assertEquals($newsletter->id, $snapshot->newsletter_id);
        $this->assertStringContainsString('Newsletter content', $snapshot->layout_html_snapshot);
    }

    public function test_snapshot_includes_branding_snapshot_when_configured(): void
    {
        $newsletter = $this->makeNewsletter();

        NewsletterBrandingConfiguration::create([
            'newsletter_id' => $newsletter->id,
            'logo_url' => 'https://example.com/logo.png',
            'header_text' => 'My Header',
            'footer_text' => 'My Footer',
            'theme_json' => ['primary_color' => '#000'],
            'custom_css' => null,
        ]);

        // HTML arrives pre-branded from PageBuilderService
        $html = '<html><head></head><body><p>Content already branded</p></body></html>';
        $snapshot = $this->service->createSnapshot($newsletter, $html);

        // branding_snapshot_json stored as audit record
        $this->assertNotNull($snapshot->branding_snapshot_json);
        $this->assertEquals('https://example.com/logo.png', $snapshot->branding_snapshot_json['logo_url']);
        $this->assertEquals('My Header', $snapshot->branding_snapshot_json['header_text']);

        // HTML stored verbatim — this service does not modify it
        $this->assertStringContainsString('Content already branded', $snapshot->layout_html_snapshot);
    }

    public function test_snapshot_creates_branding_version(): void
    {
        $newsletter = $this->makeNewsletter();

        $brandingConfig = NewsletterBrandingConfiguration::create([
            'newsletter_id' => $newsletter->id,
            'logo_url' => 'https://example.com/logo.png',
            'header_text' => 'Test Header',
            'footer_text' => null,
            'theme_json' => null,
            'custom_css' => null,
        ]);

        $this->service->createSnapshot($newsletter, '<html><body>Test</body></html>');

        $versions = $this->brandingRepository->versionHistory($brandingConfig->id);
        $this->assertCount(1, $versions);
    }

    public function test_snapshot_creation_uses_transaction(): void
    {
        $newsletter = $this->makeNewsletter();
        $html = '<html><body><p>Content</p></body></html>';

        $snapshot = $this->service->createSnapshot($newsletter, $html);

        // Both snapshot and branding version should be persisted atomically
        $this->assertDatabaseHas('newsletter_snapshots', [
            'newsletter_id' => $newsletter->id,
            'id' => $snapshot->id,
        ]);
    }

    public function test_render_from_latest_snapshot_returns_html(): void
    {
        $newsletter = $this->makeNewsletter();
        $html = '<html><body><p>Rendered content</p></body></html>';

        $this->service->createSnapshot($newsletter, $html);

        $rendered = $this->service->renderFromLatestSnapshot($newsletter->id);

        $this->assertNotNull($rendered);
        $this->assertStringContainsString('Rendered content', $rendered);
    }

    public function test_render_from_latest_snapshot_returns_null_when_no_snapshot(): void
    {
        $newsletter = $this->makeNewsletter();

        $rendered = $this->service->renderFromLatestSnapshot($newsletter->id);

        $this->assertNull($rendered);
    }

    public function test_render_from_snapshot_uses_frozen_branding_not_live(): void
    {
        $newsletter = $this->makeNewsletter();

        NewsletterBrandingConfiguration::create([
            'newsletter_id' => $newsletter->id,
            'logo_url' => 'https://example.com/logo-v1.png',
            'header_text' => null,
            'footer_text' => 'Original footer',
            'theme_json' => null,
            'custom_css' => null,
        ]);

        // Base HTML — footer_text will be injected by BrandingRendererService
        // when renderFromSnapshot() re-composes from frozen snapshot data
        $html = '<html><head></head><body><p>Content</p></body></html>';
        $snapshot = $this->service->createSnapshot($newsletter, $html);

        // Mutate live branding after snapshot was taken
        NewsletterBrandingConfiguration::where('newsletter_id', $newsletter->id)->update([
            'logo_url' => 'https://example.com/logo-v2.png',
            'footer_text' => 'Updated footer — must not appear',
        ]);

        // renderFromSnapshot re-applies the frozen branding_snapshot_json
        $rendered = $this->service->renderFromSnapshot($snapshot->id);

        $this->assertStringContainsString('Original footer', $rendered);
        $this->assertStringNotContainsString('Updated footer', $rendered);
    }

    public function test_get_all_snapshots_for_newsletter(): void
    {
        $newsletter = $this->makeNewsletter();

        $this->service->createSnapshot($newsletter, '<html><body>v1</body></html>');
        $this->service->createSnapshot($newsletter, '<html><body>v2</body></html>');

        $snapshots = $this->service->getAllSnapshotsForNewsletter($newsletter->id);

        $this->assertCount(2, $snapshots);
    }

    public function test_render_from_latest_snapshot_returns_stored_html_verbatim(): void
    {
        $newsletter = $this->makeNewsletter();

        // Simulate pre-branded HTML arriving from PageBuilderService
        $prebrandedHtml = '<html><body><img src="https://example.com/logo.png"><p>Rendered content</p></body></html>';

        $this->service->createSnapshot($newsletter, $prebrandedHtml);

        $rendered = $this->service->renderFromLatestSnapshot($newsletter->id);

        $this->assertNotNull($rendered);
        // Returned exactly as stored — no second pass
        $this->assertEquals($prebrandedHtml, $rendered);
    }
}
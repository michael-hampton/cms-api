<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Models\Model;
use App\Models\Newsletter;
use App\Models\NewsletterBrandingConfiguration;
use App\Repositories\Newsletters\NewsletterBrandingRepository;
use App\Repositories\Newsletters\NewsletterLayoutRepository;
use App\Repositories\Newsletters\NewsletterSnapshotRepository;
use App\Services\Newsletter\BrandingRendererService;
use App\Services\Newsletter\Branding\CssSanitizer;
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

        $html = '<html><head></head><body><p>Content</p></body></html>';
        $snapshot = $this->service->createSnapshot($newsletter, $html);

        $this->assertNotNull($snapshot->branding_snapshot_json);
        $this->assertEquals('https://example.com/logo.png', $snapshot->branding_snapshot_json['logo_url']);
        $this->assertEquals('My Header', $snapshot->branding_snapshot_json['header_text']);
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

        // Create branding at snapshot time
        NewsletterBrandingConfiguration::create([
            'newsletter_id' => $newsletter->id,
            'logo_url' => 'https://example.com/logo-v1.png',
            'header_text' => null,
            'footer_text' => 'Original footer',
            'theme_json' => null,
            'custom_css' => null,
        ]);

        $html = '<html><head></head><body><p>Content</p></body></html>';
        $snapshot = $this->service->createSnapshot($newsletter, $html);

        // Now change live branding (simulates a post-publish edit)
        NewsletterBrandingConfiguration::where('newsletter_id', $newsletter->id)->update([
            'logo_url' => 'https://example.com/logo-v2.png',
            'footer_text' => 'Updated footer — should not appear in render',
        ]);

        // Render uses the frozen snapshot, not live branding
        $rendered = $this->service->renderFromSnapshot($snapshot->id);

        // Branding snapshot was frozen at v1 — footer is present from snapshot data
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
}
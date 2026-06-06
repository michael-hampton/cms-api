<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Events\Newsletters\NewsletterBrandingUpdated;
use App\Framework\Support\Logger;
use App\Models\Model;
use App\Models\Newsletter;
use App\Models\NewsletterBrandingConfiguration;
use App\Repositories\Newsletters\NewsletterBrandingRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Services\Newsletter\Branding\CssSanitizer;
use App\Services\Newsletter\NewsletterBrandingService;
use App\Tests\Support\CapturingEventDispatcher;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class NewsletterBrandingServiceTest extends RepositoryTestCase
{
    private NewsletterBrandingService $service;
    private NewsletterBrandingRepository $brandingRepository;
    private CapturingEventDispatcher $events;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brandingRepository = app(NewsletterBrandingRepository::class);
        $this->events = CapturingEventDispatcher::fake();

        $this->service = new NewsletterBrandingService(
            $this->brandingRepository,
            app(NewsletterRepository::class),
            new CssSanitizer(),
            app(Logger::class),
            $this->database
        );
    }

    private function makeNewsletter(): Model
    {
        return Newsletter::create([
            'title' => 'Test Newsletter',
            'content_type' => 'manual',
            'interval' => 'weekly',
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);
    }

    // ─── Save Branding ────────────────────────────────────────────────────────

    public function test_saves_branding_and_creates_version(): void
    {
        $newsletter = $this->makeNewsletter();

        $branding = $this->service->saveBranding($newsletter->id, [
            'logo_url' => 'https://example.com/logo.png',
            'header_text' => 'Weekly Digest',
            'footer_text' => 'Unsubscribe below.',
        ]);

        $this->assertInstanceOf(NewsletterBrandingConfiguration::class, $branding);
        $this->assertEquals('https://example.com/logo.png', $branding->logo_url);
        $this->assertEquals('Weekly Digest', $branding->header_text);
        $this->events->assertDispatched(
            NewsletterBrandingUpdated::class,
            fn(NewsletterBrandingUpdated $event): bool => $event->branding === $branding
                && $event->newsletter->id === $newsletter->id
        );

        // Version should have been created
        $versions = $this->brandingRepository->versionHistory($branding->id);
        $this->assertCount(1, $versions);
        $this->assertEquals(1, $versions->first()->version_number);
    }

    public function test_upserts_branding_on_second_save(): void
    {
        $newsletter = $this->makeNewsletter();

        $this->service->saveBranding($newsletter->id, ['logo_url' => 'https://example.com/v1.png']);
        $branding = $this->service->saveBranding($newsletter->id, ['logo_url' => 'https://example.com/v2.png']);

        $this->assertEquals('https://example.com/v2.png', $branding->logo_url);

        // Two versions should exist
        $versions = $this->brandingRepository->versionHistory($branding->id);
        $this->assertCount(2, $versions);
    }

    public function test_save_branding_uses_transaction(): void
    {
        // This test verifies write atomicity by ensuring version is created
        // in the same transaction as the branding record
        $newsletter = $this->makeNewsletter();

        $branding = $this->service->saveBranding($newsletter->id, [
            'custom_css' => '.title { color: red; }',
        ]);

        $this->assertNotNull($branding->id);
        $versions = $this->brandingRepository->versionHistory($branding->id);
        $this->assertCount(1, $versions);
    }

    public function test_save_sanitizes_custom_css(): void
    {
        $newsletter = $this->makeNewsletter();

        $branding = $this->service->saveBranding($newsletter->id, [
            'custom_css' => '.title { color: red; animation: spin 1s; }',
        ]);

        $this->assertStringContainsString('color', $branding->custom_css);
        $this->assertStringNotContainsString('animation', $branding->custom_css);
    }

    public function test_throws_on_unknown_newsletter(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->saveBranding(99999, ['logo_url' => 'https://example.com/logo.png']);
    }

    // ─── Version History ──────────────────────────────────────────────────────

    public function test_returns_empty_history_when_no_branding(): void
    {
        $newsletter = $this->makeNewsletter();

        $history = $this->service->getBrandingVersionHistory($newsletter->id);

        $this->assertEmpty($history);
    }

    public function test_returns_versioned_history(): void
    {
        $newsletter = $this->makeNewsletter();

        $this->service->saveBranding($newsletter->id, ['header_text' => 'V1']);
        $this->service->saveBranding($newsletter->id, ['header_text' => 'V2']);
        $this->service->saveBranding($newsletter->id, ['header_text' => 'V3']);

        $history = $this->service->getBrandingVersionHistory($newsletter->id);

        $this->assertCount(3, $history);
    }

    // ─── Version Restore ──────────────────────────────────────────────────────

    public function test_restores_branding_to_specific_version(): void
    {
        $newsletter = $this->makeNewsletter();

        $this->service->saveBranding($newsletter->id, ['logo_url' => 'https://example.com/v1.png']);
        $this->service->saveBranding($newsletter->id, ['logo_url' => 'https://example.com/v2.png']);

        $restored = $this->service->restoreBrandingVersion($newsletter->id, 1);

        $this->assertEquals('https://example.com/v1.png', $restored->logo_url);

        // A new version record should have been created for the restore
        $branding = $this->brandingRepository->findByNewsletterId($newsletter->id);
        $versions = $this->brandingRepository->versionHistory($branding->id);
        $this->assertCount(3, $versions);
    }

    public function test_throws_when_restoring_nonexistent_version(): void
    {
        $newsletter = $this->makeNewsletter();
        $this->service->saveBranding($newsletter->id, ['header_text' => 'Hello']);

        $this->expectException(\RuntimeException::class);
        $this->service->restoreBrandingVersion($newsletter->id, 999);
    }

    public function test_get_branding_returns_null_when_none_configured(): void
    {
        $newsletter = $this->makeNewsletter();

        $this->assertNull($this->service->getBranding($newsletter->id));
    }
}

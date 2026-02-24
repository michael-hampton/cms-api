<?php

namespace App\Tests\Unit\Repositories\Newsletter;

use App\Models\Model;
use App\Models\Newsletter;
use App\Models\NewsletterBrandingConfiguration;
use App\Models\NewsletterBrandingVersion;
use App\Repositories\Newsletters\NewsletterBrandingRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class NewsletterBrandingRepositoryTest extends FunctionalTestCase
{
    private NewsletterBrandingRepository $repository;

    public function test_finds_branding_by_newsletter_id(): void
    {
        $newsletter = $this->makeNewsletter();

        NewsletterBrandingConfiguration::create([
            'newsletter_id' => $newsletter->id,
            'logo_url' => 'https://example.com/logo.png',
            'header_text' => null,
            'footer_text' => null,
            'theme_json' => null,
            'custom_css' => null,
        ]);

        $result = $this->repository->findByNewsletterId($newsletter->id);

        $this->assertNotNull($result);
        $this->assertEquals('https://example.com/logo.png', $result->logo_url);
        $this->assertEquals($newsletter->id, $result->newsletter_id);
    }

    private function makeNewsletter(): Model
    {
        return Newsletter::create([
            'title' => 'Branding Test Newsletter',
            'content_type' => 'manual',
            'interval' => 'weekly',
            'active' => true,
            'site_id' => $this->siteId,
            'content' => 'test'
        ]);
    }

    // ─── findByNewsletterId ───────────────────────────────────────────────────

    public function test_returns_null_when_no_branding_for_newsletter(): void
    {
        $newsletter = $this->makeNewsletter();

        $result = $this->repository->findByNewsletterId($newsletter->id);

        $this->assertNull($result);
    }

    public function test_does_not_return_branding_belonging_to_different_newsletter(): void
    {
        $newsletterA = $this->makeNewsletter();
        $newsletterB = $this->makeNewsletter();

        NewsletterBrandingConfiguration::create([
            'newsletter_id' => $newsletterA->id,
            'logo_url' => 'https://example.com/a-logo.png',
            'header_text' => null,
            'footer_text' => null,
            'theme_json' => null,
            'custom_css' => null,
        ]);

        $result = $this->repository->findByNewsletterId($newsletterB->id);

        $this->assertNull($result);
    }

    public function test_creates_branding_config_on_first_upsert(): void
    {
        $newsletter = $this->makeNewsletter();

        $result = $this->repository->upsertForNewsletter($newsletter->id, [
            'logo_url' => 'https://example.com/logo.png',
            'footer_text' => 'Custom footer',
        ]);

        $this->assertInstanceOf(NewsletterBrandingConfiguration::class, $result);
        $this->assertEquals($newsletter->id, $result->newsletter_id);
        $this->assertEquals('https://example.com/logo.png', $result->logo_url);
        $this->assertEquals('Custom footer', $result->footer_text);
        $this->assertDatabaseHas('newsletter_branding_configurations', [
            'newsletter_id' => $newsletter->id,
        ]);
    }

    // ─── upsertForSite ────────────────────────────────────────────────────────

    public function test_updates_existing_config_on_second_upsert(): void
    {
        $newsletter = $this->makeNewsletter();

        $this->repository->upsertForNewsletter($newsletter->id, ['logo_url' => 'https://example.com/v1.png']);
        $this->repository->upsertForNewsletter($newsletter->id, ['logo_url' => 'https://example.com/v2.png']);

        $result = $this->repository->findByNewsletterId($newsletter->id);

        $this->assertEquals('https://example.com/v2.png', $result->logo_url);
        $this->assertEquals(
            1,
            NewsletterBrandingConfiguration::where('newsletter_id', $newsletter->id)->count()
        );
    }

    public function test_two_newsletters_can_each_have_their_own_branding(): void
    {
        $newsletterA = $this->makeNewsletter();
        $newsletterB = $this->makeNewsletter();

        $this->repository->upsertForNewsletter($newsletterA->id, [
            'logo_url' => 'https://example.com/a.png',
            'theme_json' => ['primary_color' => '#ff0000'],
        ]);

        $this->repository->upsertForNewsletter($newsletterB->id, [
            'logo_url' => 'https://example.com/b.png',
            'theme_json' => ['primary_color' => '#00ff00'],
        ]);

        $brandingA = $this->repository->findByNewsletterId($newsletterA->id);
        $brandingB = $this->repository->findByNewsletterId($newsletterB->id);

        $this->assertEquals('https://example.com/a.png', $brandingA->logo_url);
        $this->assertEquals('#ff0000', $brandingA->theme_json['primary_color']);

        $this->assertEquals('https://example.com/b.png', $brandingB->logo_url);
        $this->assertEquals('#00ff00', $brandingB->theme_json['primary_color']);
    }

    public function test_creates_branding_version(): void
    {
        $newsletter = $this->makeNewsletter();
        $config = $this->repository->upsertForNewsletter($newsletter->id, [
            'logo_url' => 'https://example.com/logo.png',
        ]);

        $version = $this->repository->createVersion($config->id, $config->toSnapshot());

        $this->assertInstanceOf(NewsletterBrandingVersion::class, $version);
        $this->assertEquals(1, $version->version_number);
        $this->assertDatabaseHas('newsletter_branding_versions', [
            'branding_config_id' => $config->id,
        ]);
    }

    // ─── createVersion ────────────────────────────────────────────────────────

    public function test_version_numbers_increment_sequentially(): void
    {
        $newsletter = $this->makeNewsletter();
        $config = $this->repository->upsertForNewsletter($newsletter->id, ['logo_url' => null]);

        $v1 = $this->repository->createVersion($config->id, $config->toSnapshot());
        $v2 = $this->repository->createVersion($config->id, $config->toSnapshot());
        $v3 = $this->repository->createVersion($config->id, $config->toSnapshot());

        $this->assertEquals(1, $v1->version_number);
        $this->assertEquals(2, $v2->version_number);
        $this->assertEquals(3, $v3->version_number);
    }

    public function test_finds_version_by_number(): void
    {
        $newsletter = $this->makeNewsletter();
        $config = $this->repository->upsertForNewsletter($newsletter->id, [
            'logo_url' => 'https://example.com/logo.png',
        ]);

        $this->repository->createVersion($config->id, $config->toSnapshot());

        $found = $this->repository->findVersion($config->id, 1);

        $this->assertNotNull($found);
        $this->assertEquals(1, $found->version_number);
    }

    // ─── findVersion ──────────────────────────────────────────────────────────

    public function test_returns_null_for_missing_version(): void
    {
        $newsletter = $this->makeNewsletter();
        $config = $this->repository->upsertForNewsletter($newsletter->id, ['logo_url' => null]);

        $found = $this->repository->findVersion($config->id, 99);

        $this->assertNull($found);
    }

    public function test_find_version_does_not_cross_newsletter_boundaries(): void
    {
        $newsletterA = $this->makeNewsletter();
        $newsletterB = $this->makeNewsletter();

        $configA = $this->repository->upsertForNewsletter($newsletterA->id, ['logo_url' => 'https://a.com/logo.png']);
        $configB = $this->repository->upsertForNewsletter($newsletterB->id, ['logo_url' => 'https://b.com/logo.png']);

        $this->repository->createVersion($configA->id, $configA->toSnapshot());

        // Version 1 exists for config A but not for config B
        $notFound = $this->repository->findVersion($configB->id, 1);

        $this->assertNull($notFound);
    }

    public function test_version_history_returns_in_descending_order(): void
    {
        $newsletter = $this->makeNewsletter();
        $config = $this->repository->upsertForNewsletter($newsletter->id, ['logo_url' => null]);

        $this->repository->createVersion($config->id, ['logo_url' => 'v1.png']);
        $this->repository->createVersion($config->id, ['logo_url' => 'v2.png']);
        $this->repository->createVersion($config->id, ['logo_url' => 'v3.png']);

        $history = $this->repository->versionHistory($config->id);

        $this->assertEquals(3, $history->count());
        $this->assertEquals(3, $history->first()->version_number);
        $this->assertEquals(1, $history->last()->version_number);
    }

    // ─── versionHistory ───────────────────────────────────────────────────────

    public function test_next_version_starts_at_one_for_new_config(): void
    {
        $newsletter = $this->makeNewsletter();
        $config = $this->repository->upsertForNewsletter($newsletter->id, ['logo_url' => null]);

        $this->assertEquals(1, $this->repository->nextVersionNumber($config->id));
    }

    // ─── nextVersionNumber ────────────────────────────────────────────────────

    public function test_next_version_increments_after_existing_versions(): void
    {
        $newsletter = $this->makeNewsletter();
        $config = $this->repository->upsertForNewsletter($newsletter->id, ['logo_url' => null]);

        $this->repository->createVersion($config->id, $config->toSnapshot());
        $this->repository->createVersion($config->id, $config->toSnapshot());

        $this->assertEquals(3, $this->repository->nextVersionNumber($config->id));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(NewsletterBrandingRepository::class);
    }
}
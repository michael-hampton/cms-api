<?php

namespace App\Tests\Unit\Repositories\Newsletter;

use App\Enums\Newsletters\LayoutVersionState;
use App\Models\NewsletterLayout;
use App\Models\NewsletterLayoutVersion;
use App\Repositories\Newsletters\NewsletterLayoutRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class NewsletterLayoutRepositoryTest extends FunctionalTestCase
{
    private NewsletterLayoutRepository $repository;

    public function test_creates_version_with_correct_state(): void
    {
        $layout = NewsletterLayout::create([
            'site_id' => null,
            'name' => 'Test',
            'slug' => 'test-layout',
            'layout_definition_json' => [],
            'is_system_layout' => true,
        ]);

        $version = $this->repository->createVersion(
            $layout->id,
            ['slots' => []],
            1,
            LayoutVersionState::Draft->value
        );

        $this->assertInstanceOf(NewsletterLayoutVersion::class, $version);
        $this->assertEquals(1, $version->version_number);
        $this->assertEquals(LayoutVersionState::Draft->value, $version->state);
        $this->assertDatabaseHas('newsletter_layout_versions', ['id' => $version->id]);
    }

    // ─── createVersion ────────────────────────────────────────────────────────

    public function test_finds_layout_by_slug_for_site(): void
    {
        NewsletterLayout::create([
            'site_id' => $this->siteId,
            'name' => 'Site Layout',
            'slug' => 'site-layout',
            'layout_definition_json' => [],
            'is_system_layout' => false,
        ]);

        $found = $this->repository->findBySlugForSite('site-layout', $this->siteId);

        $this->assertNotNull($found);
        $this->assertEquals('site-layout', $found->slug);
    }

    // ─── findBySlugForSite ────────────────────────────────────────────────────

    public function test_does_not_find_layout_belonging_to_different_site(): void
    {
        $otherSiteId = $this->siteId + 999;

        NewsletterLayout::create([
            'site_id' => $otherSiteId,
            'name' => 'Other Site Layout',
            'slug' => 'other-site-slug',
            'layout_definition_json' => [],
            'is_system_layout' => false,
        ]);

        $found = $this->repository->findBySlugForSite('other-site-slug', $this->siteId);

        $this->assertNull($found);
    }

    public function test_finds_system_layout_with_null_site_id(): void
    {
        NewsletterLayout::create([
            'site_id' => null,
            'name' => 'System Layout',
            'slug' => 'system-slug',
            'layout_definition_json' => [],
            'is_system_layout' => true,
        ]);

        $found = $this->repository->findBySlugForSite('system-slug', null);

        $this->assertNotNull($found);
    }

    public function test_returns_only_system_layouts(): void
    {
        NewsletterLayout::create([
            'site_id' => null,
            'name' => 'System A',
            'slug' => 'sys-a',
            'layout_definition_json' => [],
            'is_system_layout' => true,
        ]);

        NewsletterLayout::create([
            'site_id' => $this->siteId,
            'name' => 'User Layout',
            'slug' => 'user-a',
            'layout_definition_json' => [],
            'is_system_layout' => false,
        ]);

        $results = $this->repository->allSystemLayouts();

        $this->assertGreaterThanOrEqual(1, $results->count());
        foreach ($results as $layout) {
            $this->assertTrue($layout->is_system_layout);
            $this->assertNull($layout->site_id);
        }
    }

    // ─── allSystemLayouts ─────────────────────────────────────────────────────

    public function test_returns_user_layouts_for_site_only(): void
    {
        $otherSiteId = $this->siteId + 999;

        NewsletterLayout::create([
            'site_id' => $this->siteId,
            'name' => 'My Layout',
            'slug' => 'my-layout',
            'layout_definition_json' => [],
            'is_system_layout' => false,
        ]);

        NewsletterLayout::create([
            'site_id' => $otherSiteId,
            'name' => 'Other Layout',
            'slug' => 'other-layout',
            'layout_definition_json' => [],
            'is_system_layout' => false,
        ]);

        $results = $this->repository->allUserLayouts($this->siteId);

        $this->assertGreaterThanOrEqual(1, $results->count());
        foreach ($results as $layout) {
            $this->assertEquals($this->siteId, $layout->site_id);
        }
    }

    // ─── allUserLayouts ───────────────────────────────────────────────────────

    public function test_returns_published_system_and_site_layouts(): void
    {
        $sysLayout = NewsletterLayout::create([
            'site_id' => null,
            'name' => 'Published System',
            'slug' => 'pub-sys',
            'layout_definition_json' => [],
            'is_system_layout' => true,
        ]);
        $this->repository->createVersion($sysLayout->id, [], 1, LayoutVersionState::Published->value);

        $siteLayout = NewsletterLayout::create([
            'site_id' => $this->siteId,
            'name' => 'Published Site',
            'slug' => 'pub-site',
            'layout_definition_json' => [],
            'is_system_layout' => false,
        ]);
        $this->repository->createVersion($siteLayout->id, [], 1, LayoutVersionState::Published->value);

        $unpublished = NewsletterLayout::create([
            'site_id' => $this->siteId,
            'name' => 'Draft Only',
            'slug' => 'draft-only',
            'layout_definition_json' => [],
            'is_system_layout' => false,
        ]);
        $this->repository->createVersion($unpublished->id, [], 1, LayoutVersionState::Draft->value);

        $results = $this->repository->allPublishedLayouts($this->siteId);
        $ids = $results->pluck('id')->toArray();

        $this->assertContains($sysLayout->id, $ids);
        $this->assertContains($siteLayout->id, $ids);
        $this->assertNotContains($unpublished->id, $ids);
    }

    // ─── allPublishedLayouts ──────────────────────────────────────────────────

    public function test_clone_creates_layout_belonging_to_target_site(): void
    {
        $source = NewsletterLayout::create([
            'site_id' => null,
            'name' => 'Source',
            'slug' => 'source-layout',
            'layout_definition_json' => ['slots' => [['key' => 'content']]],
            'is_system_layout' => true,
        ]);
        $this->repository->createVersion($source->id, ['slots' => [['key' => 'content']]], 1, LayoutVersionState::Published->value);

        $clone = $this->repository->cloneLayout($source->id, 'My Clone', 'my-clone', 1, $this->siteId);

        $this->assertEquals($this->siteId, $clone->site_id);
        $this->assertFalse($clone->is_system_layout);
        $this->assertNotNull($clone->latestVersion());
        $this->assertEquals(LayoutVersionState::Draft->value, $clone->latestVersion()->state);
    }

    // ─── cloneLayout ──────────────────────────────────────────────────────────

    public function test_next_version_number_starts_at_one(): void
    {
        $layout = NewsletterLayout::create([
            'site_id' => $this->siteId,
            'name' => 'New Layout',
            'slug' => 'new-layout',
            'layout_definition_json' => [],
            'is_system_layout' => false,
        ]);

        $this->assertEquals(1, $this->repository->nextVersionNumber($layout->id));
    }

    // ─── nextVersionNumber ────────────────────────────────────────────────────

    public function test_next_version_number_increments_correctly(): void
    {
        $layout = NewsletterLayout::create([
            'site_id' => $this->siteId,
            'name' => 'Versioned',
            'slug' => 'versioned-seq',
            'layout_definition_json' => [],
            'is_system_layout' => false,
        ]);

        $this->repository->createVersion($layout->id, [], 1, 'draft');
        $this->repository->createVersion($layout->id, [], 2, 'draft');

        $this->assertEquals(3, $this->repository->nextVersionNumber($layout->id));
    }

    public function test_updates_version_state(): void
    {
        $layout = NewsletterLayout::create([
            'site_id' => $this->siteId,
            'name' => 'State Layout',
            'slug' => 'state-layout',
            'layout_definition_json' => [],
            'is_system_layout' => false,
        ]);

        $version = $this->repository->createVersion($layout->id, [], 1, LayoutVersionState::Draft->value);

        $result = $this->repository->updateVersionState($version->id, LayoutVersionState::Validated);

        $this->assertTrue($result);
        $this->assertEquals(LayoutVersionState::Validated->value, $version->fresh()->state);
    }

    // ─── updateVersionState ───────────────────────────────────────────────────

    public function test_update_version_state_returns_false_for_missing_version(): void
    {
        $result = $this->repository->updateVersionState(99999, LayoutVersionState::Validated);
        $this->assertFalse($result);
    }

    public function test_version_history_returns_in_descending_order(): void
    {
        $layout = NewsletterLayout::create([
            'site_id' => $this->siteId,
            'name' => 'History Layout',
            'slug' => 'history-layout',
            'layout_definition_json' => [],
            'is_system_layout' => false,
        ]);

        $this->repository->createVersion($layout->id, [], 1, 'draft');
        $this->repository->createVersion($layout->id, [], 2, 'draft');
        $this->repository->createVersion($layout->id, [], 3, 'draft');

        $history = $this->repository->versionHistory($layout->id);

        $this->assertEquals(3, $history->count());
        $this->assertEquals(3, $history->first()->version_number);
        $this->assertEquals(1, $history->last()->version_number);
    }

    // ─── versionHistory ───────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(NewsletterLayoutRepository::class);
    }
}
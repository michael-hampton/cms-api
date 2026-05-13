<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Enums\OpenCollab\GuidelineStatus;
use App\Models\Guideline;
use App\Models\User;
use App\Models\UserGuidelinesAcknowledgement;
use App\Repositories\OpenCollab\GuidelinesContentRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class GuidelinesContentRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private GuidelinesContentRepository $repository;
    private User $user;

    // ── latestForSite ─────────────────────────────────────────────────────────

    public function test_latest_for_site_returns_highest_version_regardless_of_status(): void
    {
        Guideline::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'v1', 'status' => GuidelineStatus::Published->value]);
        Guideline::create(['site_id' => $this->siteId, 'version' => 3, 'content' => 'v3', 'status' => GuidelineStatus::Draft->value]);
        Guideline::create(['site_id' => $this->siteId, 'version' => 2, 'content' => 'v2', 'status' => GuidelineStatus::Archived->value]);

        $latest = $this->repository->latestForSite($this->siteId);

        $this->assertNotNull($latest);
        $this->assertEquals(3, $latest->version);
    }

    public function test_latest_for_site_returns_null_when_none_exist(): void
    {
        $this->assertNull($this->repository->latestForSite(999));
    }

    // ── latestPublishedForSite ────────────────────────────────────────────────

    public function test_latest_published_for_site_excludes_drafts(): void
    {
        Guideline::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'v1', 'status' => GuidelineStatus::Published->value]);
        Guideline::create(['site_id' => $this->siteId, 'version' => 2, 'content' => 'v2', 'status' => GuidelineStatus::Draft->value]);

        $latest = $this->repository->latestPublishedForSite($this->siteId);

        $this->assertNotNull($latest);
        $this->assertEquals(1, $latest->version);
        $this->assertEquals(GuidelineStatus::Published->value, $latest->status);
    }

    public function test_latest_published_for_site_excludes_archived(): void
    {
        Guideline::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'v1', 'status' => GuidelineStatus::Archived->value]);
        Guideline::create(['site_id' => $this->siteId, 'version' => 2, 'content' => 'v2', 'status' => GuidelineStatus::Published->value]);

        $latest = $this->repository->latestPublishedForSite($this->siteId);

        $this->assertEquals(2, $latest->version);
    }

    public function test_latest_published_for_site_returns_null_when_only_drafts_exist(): void
    {
        Guideline::create(['site_id' => $this->siteId, 'version' => 1, 'content' => 'v1', 'status' => GuidelineStatus::Draft->value]);

        $this->assertNull($this->repository->latestPublishedForSite($this->siteId));
    }

    // ── createVersion ─────────────────────────────────────────────────────────

    public function test_create_version_increments_from_latest(): void
    {
        Guideline::create(['site_id' => $this->siteId, 'version' => 2, 'content' => 'v2', 'status' => GuidelineStatus::Published->value]);

        $guideline = $this->repository->createVersion($this->siteId, str_repeat('x', 60));

        $this->assertEquals(3, $guideline->version);
        $this->assertEquals(GuidelineStatus::Draft->value, $guideline->status);
    }

    public function test_create_version_starts_at_one_when_no_prior_versions(): void
    {
        $guideline = $this->repository->createVersion($this->siteId, str_repeat('x', 60));

        $this->assertEquals(1, $guideline->version);
    }

    // ── publish ───────────────────────────────────────────────────────────────

    public function test_publish_transitions_draft_to_published(): void
    {
        $guideline = Guideline::create([
            'site_id' => $this->siteId,
            'version' => 1,
            'content' => 'test content',
            'status' => GuidelineStatus::Draft->value,
        ]);

        $published = $this->repository->publish($guideline, $this->user->id);

        $this->assertEquals(GuidelineStatus::Published->value, $published->status);
        $this->assertNotNull($published->published_at);
        $this->assertEquals($this->user->id, $published->published_by);
        $this->assertDatabaseHas('oc_guidelines', [
            'id' => $guideline->id,
            'status' => GuidelineStatus::Published->value,
        ]);
    }

    // ── archive ───────────────────────────────────────────────────────────────

    public function test_archive_transitions_published_to_archived(): void
    {
        $guideline = Guideline::create([
            'site_id' => $this->siteId,
            'version' => 1,
            'content' => 'test content',
            'status' => GuidelineStatus::Published->value,
        ]);

        $archived = $this->repository->archive($guideline, $this->user->id);

        $this->assertEquals(GuidelineStatus::Archived->value, $archived->status);
        $this->assertNotNull($archived->archived_at);
        $this->assertEquals($this->user->id, $archived->archived_by);
        $this->assertDatabaseHas('oc_guidelines', [
            'id' => $guideline->id,
            'status' => GuidelineStatus::Archived->value,
        ]);
    }

    // ── nextVersionNumber ─────────────────────────────────────────────────────

    public function test_next_version_number_returns_one_when_no_guidelines_exist(): void
    {
        $this->assertEquals(1, $this->repository->nextVersionNumber(999));
    }

    public function test_next_version_number_returns_latest_plus_one(): void
    {
        Guideline::create(['site_id' => $this->siteId, 'version' => 4, 'content' => 'v4', 'status' => GuidelineStatus::Published->value]);

        $this->assertEquals(5, $this->repository->nextVersionNumber($this->siteId));
    }

    // ── hasAnyAcknowledged ────────────────────────────────────────────────────

    public function test_has_any_acknowledged_returns_true_when_acknowledgement_exists(): void
    {
        $guideline = Guideline::create([
            'site_id' => $this->siteId,
            'version' => 2,
            'content' => 'test',
            'status' => GuidelineStatus::Published->value,
        ]);
        UserGuidelinesAcknowledgement::create([
            'user_id' => $this->user->id,
            'site_id' => $this->siteId,
            'version' => 2,
            'acknowledged_at' => now(),
        ]);

        $this->assertTrue($this->repository->hasAnyAcknowledged($guideline->id));
    }

    public function test_has_any_acknowledged_returns_false_when_no_acknowledgements(): void
    {
        $guideline = Guideline::create([
            'site_id' => $this->siteId,
            'version' => 2,
            'content' => 'test',
            'status' => GuidelineStatus::Draft->value,
        ]);

        $this->assertFalse($this->repository->hasAnyAcknowledged($guideline->id));
    }

    // ── Historical acknowledgement preservation ───────────────────────────────

    public function test_archived_guideline_acknowledgements_remain_queryable(): void
    {
        $guideline = Guideline::create([
            'site_id' => $this->siteId,
            'version' => 1,
            'content' => 'original',
            'status' => GuidelineStatus::Archived->value,
        ]);
        UserGuidelinesAcknowledgement::create([
            'user_id' => $this->user->id,
            'site_id' => $this->siteId,
            'version' => 1,
            'acknowledged_at' => now(),
        ]);

        // Archive does not delete acknowledgement records
        $this->assertTrue($this->repository->hasAnyAcknowledged($guideline->id));
        $this->assertDatabaseHas('oc_user_guidelines_acknowledgements', [
            'user_id' => $this->user->id,
            'version' => 1,
        ]);
    }

    // ── Setup ─────────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new GuidelinesContentRepository();
        $this->user = $this->createUser();
    }
}
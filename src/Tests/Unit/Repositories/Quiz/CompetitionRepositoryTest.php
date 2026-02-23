<?php

namespace App\Tests\Unit\Repositories\Quiz;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionNotification;
use App\Models\Site;
use App\Repositories\Quiz\CompetitionRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class CompetitionRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private CompetitionRepository $repository;

    // -------------------------------------------------------------------------
    // getActiveForSite
    // -------------------------------------------------------------------------

    public function test_get_active_for_site_returns_only_active_competitions(): void
    {
        $this->createCompetition(['site_id' => $this->siteId, 'status' => 'active']);
        $this->createCompetition(['site_id' => $this->siteId, 'status' => 'active']);
        $this->createCompetition(['site_id' => $this->siteId, 'status' => 'ended']);
        $this->createCompetition(['site_id' => $this->siteId, 'status' => 'draft']);

        $result = $this->repository->getActiveForSite($this->siteId);

        $this->assertCount(2, $result);
        foreach ($result as $comp) {
            $this->assertSame('active', $comp->status);
        }
    }

    private function createCompetition(array $attributes = []): Competition
    {
        return Competition::create(array_merge([
            'site_id' => $this->siteId,
            'title' => 'Test Competition ' . uniqid(),
            'slug' => 'test-comp-' . uniqid(),
            'status' => 'active',
            'entry_type' => 'open',
            'is_featured' => false,
            'sort_order' => 0,
            'settings' => [],
        ], $attributes));
    }

    public function test_get_active_for_site_excludes_other_sites(): void
    {
        $otherSite = Site::create(['name' => 'other', 'slug' => 'other']);

        $this->createCompetition(['site_id' => $this->siteId, 'status' => 'active']);
        $this->createCompetition(['site_id' => $otherSite->id, 'status' => 'active']);

        $result = $this->repository->getActiveForSite($this->siteId);

        $this->assertCount(1, $result);
        $this->assertSame($this->siteId, $result->first()->site_id);
    }

    public function test_get_active_for_site_orders_by_sort_order_then_starts_at(): void
    {
        $this->createCompetition(['site_id' => $this->siteId, 'status' => 'active', 'sort_order' => 2, 'starts_at' => '2025-01-01']);
        $this->createCompetition(['site_id' => $this->siteId, 'status' => 'active', 'sort_order' => 1, 'starts_at' => '2025-06-01']);
        $this->createCompetition(['site_id' => $this->siteId, 'status' => 'active', 'sort_order' => 1, 'starts_at' => '2025-03-01']);

        $result = $this->repository->getActiveForSite($this->siteId);

        $this->assertSame(1, $result->first()->sort_order);
        $this->assertSame('2025-03-01', $result->first()->starts_at->format('Y-m-d'));
        $this->assertSame('2025-06-01', $result->get(1)->starts_at->format('Y-m-d'));
        $this->assertSame(2, $result->last()->sort_order);
    }

    // -------------------------------------------------------------------------
    // getFeaturedForSite
    // -------------------------------------------------------------------------

    public function test_get_active_for_site_returns_empty_when_none_active(): void
    {
        $this->createCompetition(['site_id' => $this->siteId, 'status' => 'ended']);

        $result = $this->repository->getActiveForSite($this->siteId);

        $this->assertCount(0, $result);
    }

    public function test_get_featured_for_site_returns_featured_active_competition(): void
    {
        $featured = $this->createCompetition(['site_id' => $this->siteId, 'status' => 'active', 'is_featured' => true]);
        $this->createCompetition(['site_id' => $this->siteId, 'status' => 'active', 'is_featured' => false]);

        $result = $this->repository->getFeaturedForSite($this->siteId);

        $this->assertNotNull($result);
        $this->assertSame($featured->id, $result->id);
    }

    public function test_get_featured_for_site_returns_null_when_no_featured(): void
    {
        $this->createCompetition(['site_id' => $this->siteId, 'status' => 'active', 'is_featured' => false]);

        $result = $this->repository->getFeaturedForSite($this->siteId);

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // findBySlug
    // -------------------------------------------------------------------------

    public function test_get_featured_for_site_excludes_non_active(): void
    {
        $this->createCompetition(['site_id' => $this->siteId, 'status' => 'ended', 'is_featured' => true]);

        $result = $this->repository->getFeaturedForSite($this->siteId);

        $this->assertNull($result);
    }

    public function test_find_by_slug_returns_competition_when_exists(): void
    {
        $comp = $this->createCompetition(['site_id' => $this->siteId, 'slug' => 'win-a-ps5']);

        $result = $this->repository->findBySlug('win-a-ps5');

        $this->assertNotNull($result);
        $this->assertSame($comp->id, $result->id);
    }

    public function test_find_by_slug_returns_null_when_not_found(): void
    {
        $result = $this->repository->findBySlug($this->siteId, 'does-not-exist');

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // findEntry
    // -------------------------------------------------------------------------

    public function test_find_by_slug_is_scoped_to_site(): void
    {
        $otherSite = Site::create(['name' => 'other', 'slug' => 'other']);
        $this->createCompetition(['site_id' => $otherSite->id, 'slug' => 'win-a-prize']);

        $result = $this->repository->findBySlug($this->siteId, 'win-a-prize');

        $this->assertNull($result);
    }

    public function test_find_entry_returns_entry_when_exists(): void
    {
        $member = $this->createMember();
        $comp = $this->createCompetition();

        CompetitionEntry::create([
            'competition_id' => $comp->id,
            'member_id' => $member->id,
            'entered_at' => now_datetime(),
            'entry_method' => 'open',
        ]);

        $result = $this->repository->findEntry($comp->id, $member->id);

        $this->assertNotNull($result);
        $this->assertSame($member->id, $result->member_id);
    }

    public function test_find_entry_returns_null_when_not_entered(): void
    {
        $member = $this->createMember();
        $comp = $this->createCompetition();

        $result = $this->repository->findEntry($comp->id, $member->id);

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // createEntry
    // -------------------------------------------------------------------------

    public function test_find_entry_is_scoped_to_competition(): void
    {
        $member = $this->createMember();
        $comp1 = $this->createCompetition();
        $comp2 = $this->createCompetition();

        CompetitionEntry::create([
            'competition_id' => $comp1->id,
            'member_id' => $member->id,
            'entered_at' => now_datetime(),
            'entry_method' => 'open',
        ]);

        $result = $this->repository->findEntry($comp2->id, $member->id);

        $this->assertNull($result);
    }

    public function test_create_entry_persists_record(): void
    {
        $member = $this->createMember();
        $comp = $this->createCompetition();

        $entry = $this->repository->createEntry([
            'competition_id' => $comp->id,
            'member_id' => $member->id,
            'entered_at' => now_datetime(),
            'entry_method' => 'open',
        ]);

        $this->assertNotNull($entry->id);
        $this->assertDatabaseHas('competition_entries', [
            'competition_id' => $comp->id,
            'member_id' => $member->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // getEntryCount
    // -------------------------------------------------------------------------

    private function createEntry(array $attributes): CompetitionEntry
    {
        return CompetitionEntry::create(array_merge([
            'entered_at' => now_datetime(),
            'entry_method' => 'open',
        ], $attributes));
    }

    public function test_create_entry_stores_referred_by_member_id(): void
    {
        $member = $this->createMember();
        $referrer = $this->createMember();
        $comp = $this->createCompetition();

        $entry = $this->repository->createEntry([
            'competition_id' => $comp->id,
            'member_id' => $member->id,
            'entered_at' => now_datetime(),
            'entry_method' => 'referral',
            'referred_by_member_id' => $referrer->id,
        ]);

        $this->assertSame($referrer->id, $entry->referred_by_member_id);
    }

    public function test_get_entry_count_returns_correct_count(): void
    {
        $member1 = $this->createMember();
        $member2 = $this->createMember();
        $comp = $this->createCompetition();

        $this->createEntry(['competition_id' => $comp->id, 'member_id' => $member1->id]);
        $this->createEntry(['competition_id' => $comp->id, 'member_id' => $member2->id]);

        $this->assertSame(2, $this->repository->getEntryCount($comp->id));
    }

    // -------------------------------------------------------------------------
    // findNotification
    // -------------------------------------------------------------------------

    public function test_get_entry_count_returns_zero_when_no_entries(): void
    {
        $comp = $this->createCompetition();

        $this->assertSame(0, $this->repository->getEntryCount($comp->id));
    }

    public function test_get_entry_count_is_scoped_to_competition(): void
    {
        $member = $this->createMember();
        $comp1 = $this->createCompetition();
        $comp2 = $this->createCompetition();

        $this->createEntry(['competition_id' => $comp1->id, 'member_id' => $member->id]);

        $this->assertSame(0, $this->repository->getEntryCount($comp2->id));
    }

    public function test_find_notification_returns_notification_when_exists(): void
    {
        $member = $this->createMember();
        $comp = $this->createCompetition();

        CompetitionNotification::create([
            'competition_id' => $comp->id,
            'member_id' => $member->id,
            'notified_at' => now_datetime(),
        ]);

        $result = $this->repository->findNotification($comp->id, $member->id);

        $this->assertNotNull($result);
    }

    // -------------------------------------------------------------------------
    // createNotification
    // -------------------------------------------------------------------------

    public function test_find_notification_returns_null_when_not_registered(): void
    {
        $member = $this->createMember();
        $comp = $this->createCompetition();

        $result = $this->repository->findNotification($comp->id, $member->id);

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // getMembersToNotify
    // -------------------------------------------------------------------------

    public function test_find_notification_is_scoped_to_competition(): void
    {
        $member = $this->createMember();
        $comp1 = $this->createCompetition();
        $comp2 = $this->createCompetition();

        CompetitionNotification::create([
            'competition_id' => $comp1->id,
            'member_id' => $member->id,
            'notified_at' => now_datetime(),
        ]);

        $this->assertNull($this->repository->findNotification($comp2->id, $member->id));
    }

    public function test_create_notification_persists_record(): void
    {
        $member = $this->createMember();
        $comp = $this->createCompetition();

        $notification = $this->repository->createNotification([
            'competition_id' => $comp->id,
            'member_id' => $member->id,
            'notified_at' => now_datetime(),
        ]);

        $this->assertNotNull($notification->id);
        $this->assertDatabaseHas('competition_notifications', [
            'competition_id' => $comp->id,
            'member_id' => $member->id,
        ]);
    }

    public function test_get_members_to_notify_returns_all_registered_members(): void
    {
        $member1 = $this->createMember();
        $member2 = $this->createMember();
        $comp = $this->createCompetition();

        CompetitionNotification::create(['competition_id' => $comp->id, 'member_id' => $member1->id, 'notified_at' => now_datetime()]);
        CompetitionNotification::create(['competition_id' => $comp->id, 'member_id' => $member2->id, 'notified_at' => now_datetime()]);

        $result = $this->repository->getMembersToNotify($comp->id);

        $this->assertCount(2, $result);
    }

    // -------------------------------------------------------------------------
    // setWinner
    // -------------------------------------------------------------------------

    public function test_get_members_to_notify_eager_loads_member(): void
    {
        $member = $this->createMember();
        $comp = $this->createCompetition();

        CompetitionNotification::create(['competition_id' => $comp->id, 'member_id' => $member->id, 'notified_at' => now_datetime()]);

        $result = $this->repository->getMembersToNotify($comp->id);

        $this->assertTrue($result->first()->relationLoaded('member'));
    }

    public function test_get_members_to_notify_is_scoped_to_competition(): void
    {
        $member = $this->createMember();
        $comp1 = $this->createCompetition();
        $comp2 = $this->createCompetition();

        CompetitionNotification::create(['competition_id' => $comp1->id, 'member_id' => $member->id, 'notified_at' => now_datetime()]);

        $this->assertCount(0, $this->repository->getMembersToNotify($comp2->id));
    }

    public function test_set_winner_updates_winner_member_id(): void
    {
        $member = $this->createMember();
        $comp = $this->createCompetition(['status' => 'active']);

        $updated = $this->repository->setWinner($comp->id, $member->id);

        $this->assertSame($member->id, $updated->winner_member_id);
    }

    // -------------------------------------------------------------------------
    // Fixtures
    // -------------------------------------------------------------------------

    public function test_set_winner_sets_status_to_ended(): void
    {
        $member = $this->createMember();
        $comp = $this->createCompetition(['status' => 'active']);

        $updated = $this->repository->setWinner($comp->id, $member->id);

        $this->assertSame('ended', $updated->status);
    }

    public function test_set_winner_persists_to_database(): void
    {
        $member = $this->createMember();
        $comp = $this->createCompetition(['status' => 'active']);

        $this->repository->setWinner($comp->id, $member->id);

        $this->assertDatabaseHas('competitions', [
            'id' => $comp->id,
            'winner_member_id' => $member->id,
            'status' => 'ended',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new CompetitionRepository();
    }
}
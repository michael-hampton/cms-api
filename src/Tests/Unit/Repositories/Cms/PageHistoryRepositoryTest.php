<?php

namespace App\Tests\Unit\Repositories\Cms;

use App\Models\PageHistory;
use App\Repositories\Cms\PageHistoryRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class PageHistoryRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private PageHistoryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PageHistoryRepository();
    }

    public function test_get_page_history_returns_history_for_page(): void
    {
        $page = $this->createPage();
        $user = $this->createUser();

        $this->createPageHistory($page->id, $user->id, ['action' => 'created']);
        $this->createPageHistory($page->id, $user->id, ['action' => 'updated']);

        $history = $this->repository->getPageHistory($page->id);

        $this->assertCount(2, $history);
    }

    public function test_get_page_history_ordered_by_created_at_desc(): void
    {
        $page = $this->createPage();
        $user = $this->createUser();

        $older = $this->createPageHistory($page->id, $user->id, [
            'action' => 'created',
            'created_at' => '2024-01-01 00:00:00'
        ]);
        $newer = $this->createPageHistory($page->id, $user->id, [
            'action' => 'updated',
            'created_at' => '2024-12-31 23:59:59'
        ]);

        $history = $this->repository->getPageHistory($page->id);

        $this->assertEquals($newer->id, $history->first()->id);
        $this->assertEquals($older->id, $history->last()->id);
    }

    public function test_get_page_history_respects_limit(): void
    {
        $page = $this->createPage();
        $user = $this->createUser();

        for ($i = 0; $i < 60; $i++) {
            $this->createPageHistory($page->id, $user->id);
        }

        $history = $this->repository->getPageHistory($page->id, 25);

        $this->assertCount(25, $history);
    }

    public function test_get_page_history_loads_user_relationship(): void
    {
        $page = $this->createPage();
        $user = $this->createUser();
        $this->createPageHistory($page->id, $user->id);

        $history = $this->repository->getPageHistory($page->id);

        $this->assertRelationLoaded($history->first(), 'user');

        $this->assertEquals($user->id, $history->first()->user['id']);
    }

    public function test_get_recent_history_returns_history_for_site(): void
    {
        $user = $this->createUser();
        $page1 = $this->createPage(['site_id' => $this->siteId]);
        $page2 = $this->createPage(['site_id' => $this->siteId]);

        $this->createPageHistory($page1->id, $user->id);
        $this->createPageHistory($page2->id, $user->id);

        $history = $this->repository->getRecentHistory($this->siteId);

        $this->assertCount(2, $history);
    }

    public function test_get_recent_history_filters_by_site(): void
    {
        $otherSite = $this->createSite();
        $user = $this->createUser();

        $page1 = $this->createPage(['site_id' => $this->siteId]);
        $page2 = $this->createPage(['site_id' => $otherSite->id]);

        $this->createPageHistory($page1->id, $user->id, ['site_id' => $this->siteId]);
        $this->createPageHistory($page2->id, $user->id, ['site_id' => $otherSite->id]);

        $history = $this->repository->getRecentHistory($this->siteId);

        $this->assertCount(1, $history);
        foreach ($history as $entry) {
            $this->assertEquals($this->siteId, $entry->site_id);
        }
    }

    public function test_get_recent_history_respects_limit(): void
    {
        $user = $this->createUser();

        for ($i = 0; $i < 30; $i++) {
            $page = $this->createPage();
            $this->createPageHistory($page->id, $user->id);
        }

        $history = $this->repository->getRecentHistory($this->siteId, 10);

        $this->assertCount(10, $history);
    }

    public function test_get_recent_history_loads_relationships(): void
    {
        $user = $this->createUser();
        $page = $this->createPage();
        $this->createPageHistory($page->id, $user->id);

        $history = $this->repository->getRecentHistory($this->siteId);

        $entry = $history->first();
        $this->assertRelationLoaded($entry, 'page');
        $this->assertRelationLoaded($entry, 'user');
    }

    public function test_get_user_history_returns_history_for_user(): void
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $page = $this->createPage();

        $this->createPageHistory($page->id, $user1->id);
        $this->createPageHistory($page->id, $user1->id);
        $this->createPageHistory($page->id, $user2->id);

        $history = $this->repository->getUserHistory($user1->id);

        $this->assertCount(2, $history);
        foreach ($history as $entry) {
            $this->assertEquals($user1->id, $entry->user_id);
        }
    }

    public function test_get_user_history_respects_limit(): void
    {
        $user = $this->createUser();

        for ($i = 0; $i < 60; $i++) {
            $page = $this->createPage();
            $this->createPageHistory($page->id, $user->id);
        }

        $history = $this->repository->getUserHistory($user->id, 30);

        $this->assertCount(30, $history);
    }

    public function test_get_history_by_action_filters_correctly(): void
    {
        $page = $this->createPage();
        $user = $this->createUser();

        $this->createPageHistory($page->id, $user->id, ['action' => 'created']);
        $this->createPageHistory($page->id, $user->id, ['action' => 'updated']);
        $this->createPageHistory($page->id, $user->id, ['action' => 'updated']);
        $this->createPageHistory($page->id, $user->id, ['action' => 'deleted']);

        $history = $this->repository->getHistoryByAction($page->id, 'updated');

        $this->assertCount(2, $history);
        foreach ($history as $entry) {
            $this->assertEquals('updated', $entry->action);
        }
    }

    public function test_get_history_between_dates(): void
    {
        $page = $this->createPage();
        $user = $this->createUser();

        $this->createPageHistory($page->id, $user->id, [
            'created_at' => '2024-01-01 00:00:00'
        ]);
        $this->createPageHistory($page->id, $user->id, [
            'created_at' => '2024-06-15 12:00:00'
        ]);
        $this->createPageHistory($page->id, $user->id, [
            'created_at' => '2024-12-31 23:59:59'
        ]);

        $history = $this->repository->getHistoryBetween(
            $page->id,
            '2024-06-01 00:00:00',
            '2024-12-01 00:00:00'
        );

        $this->assertCount(1, $history);

        $this->assertEquals('2024-06-15 12:00:00', $history->first()->created_at->format('Y-m-d H:i:s'));
    }

    public function test_find_by_id_returns_history_with_relationships(): void
    {
        $page = $this->createPage();
        $user = $this->createUser();
        $history = $this->createPageHistory($page->id, $user->id);

        $found = $this->repository->findById($history->id);

        $this->assertNotNull($found);
        $this->assertEquals($history->id, $found->id);
        $this->assertRelationLoaded($found, 'page');
        $this->assertRelationLoaded($found, 'user');
    }

    public function test_delete_page_history_removes_all_for_page(): void
    {
        $page = $this->createPage();
        $user = $this->createUser();

        $this->createPageHistory($page->id, $user->id);
        $this->createPageHistory($page->id, $user->id);
        $this->createPageHistory($page->id, $user->id);

        $result = $this->repository->deletePageHistory($page->id);

        $this->assertTrue($result);
        $count = $this->countRecords('page_history', ['page_id' => $page->id]);
        $this->assertEquals(0, $count);
    }

    public function test_delete_older_than_removes_old_entries(): void
    {
        $page = $this->createPage();
        $user = $this->createUser();

        // Old entries (31+ days ago)
        $this->createPageHistory($page->id, $user->id, [
            'created_at' => date('Y-m-d H:i:s', strtotime('-35 days'))
        ]);
        $this->createPageHistory($page->id, $user->id, [
            'created_at' => date('Y-m-d H:i:s', strtotime('-31 days'))
        ]);

        // Recent entries
        $this->createPageHistory($page->id, $user->id, [
            'created_at' => date('Y-m-d H:i:s', strtotime('-29 days'))
        ]);
        $this->createPageHistory($page->id, $user->id, [
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ]);

        $deleted = $this->repository->deleteOlderThan(30);

        $this->assertEquals(2, $deleted);
        $remaining = PageHistory::where('page_id', $page->id)->count();
        $this->assertEquals(2, $remaining);
    }
}
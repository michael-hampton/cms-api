<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Models\UserSite;
use App\Repositories\OpenCollab\AdminContributorRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class AdminContributorRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private AdminContributorRepository $repository;

    public function test_search_for_site_returns_only_matching_contributors_with_site_access(): void
    {
        $matching = $this->createUser([
            'name' => 'Alice Contributor',
            'email' => 'alice@example.com',
            'role' => 'contributor',
        ]);
        $nonMatching = $this->createUser([
            'name' => 'Bob Writer',
            'email' => 'bob@example.com',
            'role' => 'contributor',
        ]);
        $nonContributor = $this->createUser([
            'name' => 'Alice Admin',
            'email' => 'alice-admin@example.com',
            'role' => 'user',
        ]);

        UserSite::create(['user_id' => $matching->id, 'site_id' => $this->siteId]);
        UserSite::create(['user_id' => $nonMatching->id, 'site_id' => $this->siteId]);
        UserSite::create(['user_id' => $nonContributor->id, 'site_id' => $this->siteId]);

        $results = $this->repository->searchForSite($this->siteId, 'Alice', 10);
        $item = $results['data']->all()[0];

        $this->assertCount(1, $results['data']->all());
        $this->assertEquals($matching->id, is_array($item) ? $item['id'] : $item->id);
    }

    public function test_find_contributor_for_site_returns_null_when_user_is_not_on_site(): void
    {
        $user = $this->createUser(['is_contributor' => true]);

        $found = $this->repository->findContributorForSite($user->id, $this->siteId);

        $this->assertNull($found);
    }

    public function test_pending_closure_for_site_returns_inactive_contributors_newest_first(): void
    {
        $older = $this->createUser([
            'is_contributor' => true,
            'is_active' => false,
        ]);
        $this->database->query('UPDATE users SET updated_at = ? WHERE id = ?', ['2024-01-01 00:00:00', $older->id]);
        $newer = $this->createUser([
            'is_contributor' => true,
            'is_active' => false,
        ]);
        $this->database->query('UPDATE users SET updated_at = ? WHERE id = ?', ['2024-06-01 00:00:00', $newer->id]);
        $active = $this->createUser([
            'is_contributor' => true,
            'is_active' => true,
        ]);

        UserSite::create(['user_id' => $older->id, 'site_id' => $this->siteId]);
        UserSite::create(['user_id' => $newer->id, 'site_id' => $this->siteId]);
        UserSite::create(['user_id' => $active->id, 'site_id' => $this->siteId]);

        $results = $this->repository->pendingClosureForSite($this->siteId);
        $first = $results->first();

        $this->assertCount(2, $results);
        $this->assertEquals($newer->id, is_array($first) ? $first['id'] : $first->id);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new AdminContributorRepository();
    }
}

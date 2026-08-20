<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Models\User;
use App\Models\UserSite;
use App\Repositories\OpenCollab\UserSiteRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class UserSiteRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private UserSiteRepository $repository;
    private User $user;

    public function test_has_access_returns_true_when_record_exists(): void
    {
        $this->assertTrue($this->repository->hasAccess($this->user->id, $this->siteId));
    }

    public function test_has_access_returns_false_when_no_record(): void
    {
        $otherSite = $this->createSite();

        $this->assertFalse($this->repository->hasAccess($this->user->id, $otherSite->id));
    }

    public function test_grant_creates_record(): void
    {
        $otherSite = $this->createSite();

        $this->repository->grant($this->user->id, $otherSite->id);

        $this->assertDatabaseHas('oc_user_sites', ['user_id' => $this->user->id, 'site_id' => $otherSite->id]);
    }

    public function test_grant_does_not_create_duplicate(): void
    {
        $this->repository->grant($this->user->id, $this->siteId);
        $this->repository->grant($this->user->id, $this->siteId);

        $this->assertEquals(1, $this->countRecords('oc_user_sites', [
            'user_id' => $this->user->id,
            'site_id' => $this->siteId,
        ]));
    }

    public function test_revoke_removes_record(): void
    {
        $this->repository->revoke($this->user->id, $this->siteId);

        $this->assertDatabaseMissing('oc_user_sites', ['user_id' => $this->user->id, 'site_id' => $this->siteId]);
    }

    public function test_revoke_does_not_remove_other_records(): void
    {
        $otherSite = $this->createSite();
        UserSite::create(['user_id' => $this->user->id, 'site_id' => $otherSite->id]);

        $this->repository->revoke($this->user->id, $this->siteId);

        $this->assertDatabaseHas('oc_user_sites', ['user_id' => $this->user->id, 'site_id' => $otherSite->id]);
    }

    public function test_site_ids_for_user_returns_all_site_ids(): void
    {
        $otherSite2 = $this->createSite();
        $otherSite3 = $this->createSite();
        $user2 = $this->createUser();
        UserSite::create(['user_id' => $this->user->id, 'site_id' => $otherSite2->id]);
        UserSite::create(['user_id' => $user2->id, 'site_id' => $otherSite3->id]);

        $ids = $this->repository->siteIdsForUser($this->user->id);

        $this->assertEqualsCanonicalizing([$this->siteId, $otherSite2->id], $ids);
    }

    public function test_site_ids_for_user_returns_empty_array_when_none(): void
    {
        $this->assertEquals([], $this->repository->siteIdsForUser(999));
    }

    public function test_site_ids_are_cast_to_integers(): void
    {
        $ids = $this->repository->siteIdsForUser($this->user->id);

        $this->assertIsInt($ids[0]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserSiteRepository();
        $this->user = $this->createUser();
    }
}
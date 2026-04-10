<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Models\User;
use App\Models\UserGuidelinesAcknowledgement;
use App\Repositories\OpenCollab\GuidelinesRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class GuidelinesRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private GuidelinesRepository $repository;
    private User $user;

    public function test_has_acknowledged_returns_true_when_record_exists(): void
    {
        UserGuidelinesAcknowledgement::create(['user_id' => $this->user->id, 'site_id' => $this->siteId, 'version' => 2, 'acknowledged_at' => now()]);

        $this->assertTrue($this->repository->hasAcknowledged($this->user->id, $this->siteId, 2));
    }

    public function test_has_acknowledged_returns_false_when_no_record(): void
    {
        $this->assertFalse($this->repository->hasAcknowledged($this->user->id, $this->siteId, 2));
    }

    public function test_has_acknowledged_is_version_specific(): void
    {
        UserGuidelinesAcknowledgement::create(['user_id' => $this->user->id, 'site_id' => $this->siteId, 'version' => 1, 'acknowledged_at' => now()]);

        $this->assertFalse($this->repository->hasAcknowledged($this->user->id, $this->siteId, 2));
    }

    public function test_latest_acknowledged_version_returns_highest_version(): void
    {
        UserGuidelinesAcknowledgement::create(['user_id' => $this->user->id, 'site_id' => $this->siteId, 'version' => 1, 'acknowledged_at' => now()]);
        UserGuidelinesAcknowledgement::create(['user_id' => $this->user->id, 'site_id' => $this->siteId, 'version' => 3, 'acknowledged_at' => now()]);
        UserGuidelinesAcknowledgement::create(['user_id' => $this->user->id, 'site_id' => $this->siteId, 'version' => 2, 'acknowledged_at' => now()]);

        $this->assertEquals(3, $this->repository->latestAcknowledgedVersion($this->user->id, $this->siteId));
    }

    public function test_latest_acknowledged_version_returns_zero_when_none(): void
    {
        $this->assertEquals(0, $this->repository->latestAcknowledgedVersion(1, 1));
    }

    public function test_record_persists_acknowledgement(): void
    {
        $ack = $this->repository->record($this->user->id, $this->siteId, 4);

        $this->assertInstanceOf(UserGuidelinesAcknowledgement::class, $ack);
        $this->assertDatabaseHas('oc_user_guidelines_acknowledgements', [
            'user_id' => $this->user->id,
            'site_id' => $this->siteId,
            'version' => 4,
        ]);
        $this->assertNotNull($ack->acknowledged_at);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new GuidelinesRepository();
        $this->user = $this->createUser();
    }
}
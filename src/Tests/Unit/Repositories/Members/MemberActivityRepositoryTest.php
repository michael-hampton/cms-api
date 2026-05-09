<?php

namespace App\Tests\Unit\Repositories\Members;

use App\Models\MemberActivity;
use App\Models\Model;
use App\Repositories\MemberInsights\MemberActivityRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class MemberActivityRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private MemberActivityRepository $repository;

    public function testGetMemberActivities()
    {
        $member = $this->createMember();

        for ($i = 0; $i < 10; $i++) {
            MemberActivity::create([
                'member_id' => $member->id,
                'site_id' => $this->siteId,
                'activity_type' => 'comment',
                'activity_date' => now_datetime()->subDays($i)
            ]);
        }

        $activities = $this->repository->getMemberActivities($member->id, 5);

        $this->assertCount(5, $activities);
    }

    public function testGetMemberActivitiesOrderedByDate()
    {
        $member = $this->createMember();

        $activity1 = MemberActivity::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'activity_type' => 'comment',
            'activity_date' => now_datetime()->subDays(5)->format('Y-m-d H:i:s')
        ]);

        $activity2 = MemberActivity::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'activity_type' => 'like',
            'activity_date' => now_datetime()->subDays(1)
        ]);

        $activities = $this->repository->getMemberActivities($member->id);

        $this->assertEquals($activity2->id, $activities->first()->id);
    }

    public function testGetActivityStats()
    {
        $member = $this->createMember();

        for ($i = 0; $i < 5; $i++) {
            MemberActivity::create([
                'member_id' => $member->id,
                'site_id' => $this->siteId,
                'activity_type' => 'comment',
                'activity_date' => now_datetime()->subDays($i)->format('Y-m-d H:i:s')
            ]);
        }

        for ($i = 0; $i < 3; $i++) {
            MemberActivity::create([
                'member_id' => $member->id,
                'site_id' => $this->siteId,
                'activity_type' => 'like',
                'activity_date' => now_datetime()->subDays($i)
            ]);
        }

        $stats = $this->repository->getActivityStats($member->id, 30);

        $this->assertEquals(8, $stats['total']);
        $this->assertEquals(5, $stats['by_type']['comment']);
        $this->assertEquals(3, $stats['by_type']['like']);
    }

    public function testGetActivityStatsRespectsDaysLimit()
    {
        $member = $this->createMember();

        MemberActivity::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'activity_type' => 'comment',
            'activity_date' => now_datetime()->subDays(5)->format('Y-m-d H:i:s')
        ]);

        MemberActivity::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'activity_type' => 'comment',
            'activity_date' => now_datetime()->subDays(40)
        ]);

        $stats = $this->repository->getActivityStats($member->id, 30);

        $this->assertEquals(1, $stats['total']);
    }

    public function test_get_paginated_returns_only_that_members_activities(): void
    {
        $m1 = $this->createMember();
        $m2 = $this->createMember();

        $this->createActivity($m1->id);
        $this->createActivity($m1->id);
        $this->createActivity($m2->id);

        $result = $this->repository->getPaginatedForMember($m1->id, 1, 20);

        $this->assertEquals(2, $result['total']);
        foreach ($result['data'] as $activity) {
            $this->assertEquals($m1->id, $activity->member_id);
        }
    }

    public function test_get_paginated_respects_per_page(): void
    {
        $member = $this->createMember();

        for ($i = 0; $i < 5; $i++) {
            $this->createActivity($member->id);
        }

        $result = $this->repository->getPaginatedForMember($member->id, 1, 3);

        $this->assertCount(3, $result['data']);
        $this->assertEquals(5, $result['total']);
        $this->assertEquals(2, $result['last_page']);
    }

    public function test_get_paginated_returns_second_page(): void
    {
        $member = $this->createMember();

        for ($i = 0; $i < 5; $i++) {
            $this->createActivity($member->id);
        }

        $result = $this->repository->getPaginatedForMember($member->id, 2, 3);

        $this->assertCount(2, $result['data']);
    }

    public function test_get_paginated_returns_empty_when_no_activities(): void
    {
        $member = $this->createMember();

        $result = $this->repository->getPaginatedForMember($member->id, 1, 20);

        $this->assertEquals(0, $result['total']);
        $this->assertEmpty($result['data']);
        $this->assertEquals(1, $result['last_page']);
    }

    public function test_get_paginated_last_page_never_below_one(): void
    {
        $member = $this->createMember();

        $result = $this->repository->getPaginatedForMember($member->id, 1, 20);

        $this->assertGreaterThanOrEqual(1, $result['last_page']);
    }

    private function createActivity(int $memberId, array $overrides = []): Model
    {
        return MemberActivity::create(array_merge([
            'member_id' => $memberId,
            'site_id' => $this->siteId,
            'activity_type' => 'comment',
            'activity_date' => now_datetime()->toDateTimeString(),
            'created_at' => now_datetime()->toDateTimeString(),
        ], $overrides));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MemberActivityRepository();
    }
}
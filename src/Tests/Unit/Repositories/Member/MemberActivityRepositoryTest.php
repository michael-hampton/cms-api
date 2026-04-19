<?php

namespace App\Tests\Unit\Repositories\Member;

use App\Models\MemberActivity;
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MemberActivityRepository();
    }
}
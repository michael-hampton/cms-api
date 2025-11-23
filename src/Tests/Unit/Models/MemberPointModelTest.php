<?php

namespace App\Tests\Unit\Models;

use App\Models\Member;
use App\Models\MemberPoint;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberPointModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testCreateMemberPoint()
    {
        $member = $this->createMember();

        $point = MemberPoint::create([
            'member_id' => $member->id,
            'points' => 50,
            'reason' => 'Badge earned: First Comment',
            'reference_type' => 'badge',
            'reference_id' => 1,
            'awarded_at' => now()
        ]);

        $this->assertInstanceOf(MemberPoint::class, $point);
        $this->assertEquals(50, $point->points);
        $this->assertEquals('Badge earned: First Comment', $point->reason);
    }

    public function testMemberPointBelongsToMember()
    {
        $member = $this->createMember();

        $point = MemberPoint::create([
            'member_id' => $member->id,
            'points' => 10,
            'reason' => 'Activity: comment',
            'awarded_at' => now()
        ]);

        $this->assertInstanceOf(Member::class, $point->member());
        $this->assertEquals($member->id, $point->member()->id);
    }

    public function testPointsCast()
    {
        $member = $this->createMember();

        $point = MemberPoint::create([
            'member_id' => $member->id,
            'points' => '25',
            'reason' => 'Test',
            'awarded_at' => now()
        ]);

        $this->assertIsInt($point->points);
        $this->assertEquals(25, $point->points);
    }

    public function testAwardedAtCast()
    {
        $member = $this->createMember();

        $point = MemberPoint::create([
            'member_id' => $member->id,
            'points' => 15,
            'reason' => 'Test',
            'awarded_at' => now()
        ]);

        $this->assertInstanceOf(\DateTime::class, $point->awarded_at);
    }

    public function testNegativePoints()
    {
        $member = $this->createMember();

        $point = MemberPoint::create([
            'member_id' => $member->id,
            'points' => -10,
            'reason' => 'Penalty',
            'awarded_at' => now()
        ]);

        $this->assertEquals(-10, $point->points);
    }

    public function testTimestamps()
    {
        $member = $this->createMember();

        $point = MemberPoint::create([
            'member_id' => $member->id,
            'points' => 20,
            'reason' => 'Test',
            'awarded_at' => now()
        ]);

        $this->assertNotNull($point->created_at);
        $this->assertNotNull($point->updated_at);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }
}
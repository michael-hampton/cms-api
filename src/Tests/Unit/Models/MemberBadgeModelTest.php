<?php

namespace App\Tests\Unit\Models;

use App\Models\Badge;
use App\Models\Member;
use App\Models\MemberBadge;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberBadgeModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testCreateMemberBadge()
    {
        $member = $this->createMember();
        $badge = $this->createBadge();

        $memberBadge = MemberBadge::create([
            'member_id' => $member->id,
            'badge_id' => $badge->id,
            'earned_at' => now(),
            'is_visible' => true
        ]);

        $this->assertInstanceOf(MemberBadge::class, $memberBadge);
        $this->assertEquals($member->id, $memberBadge->member_id);
        $this->assertEquals($badge->id, $memberBadge->badge_id);
    }

    public function testMemberBadgeBelongsToMember()
    {
        $member = $this->createMember();
        $badge = $this->createBadge();

        $memberBadge = MemberBadge::create([
            'member_id' => $member->id,
            'badge_id' => $badge->id,
            'earned_at' => now()
        ]);

        $this->assertInstanceOf(Member::class, $memberBadge->member());
        $this->assertEquals($member->id, $memberBadge->member()->id);
    }

    public function testMemberBadgeBelongsToBadge()
    {
        $member = $this->createMember();
        $badge = $this->createBadge();

        $memberBadge = MemberBadge::create([
            'member_id' => $member->id,
            'badge_id' => $badge->id,
            'earned_at' => now()
        ]);

        $this->assertInstanceOf(Badge::class, $memberBadge->badge());
        $this->assertEquals($badge->id, $memberBadge->badge()->id);
    }

    public function testCriteriaMetCast()
    {
        $member = $this->createMember();
        $badge = $this->createBadge();

        $criteria = ['comments_count' => 10, 'likes_given' => 5];

        $memberBadge = MemberBadge::create([
            'member_id' => $member->id,
            'badge_id' => $badge->id,
            'earned_at' => now(),
            'criteria_met' => $criteria
        ]);

        $this->assertIsArray($memberBadge->criteria_met);
        $this->assertEquals($criteria, $memberBadge->criteria_met);
    }

    public function testIsVisibleCast()
    {
        $member = $this->createMember();
        $badge = $this->createBadge();

        $memberBadge = MemberBadge::create([
            'member_id' => $member->id,
            'badge_id' => $badge->id,
            'earned_at' => now(),
            'is_visible' => 1
        ]);

        $this->assertIsBool($memberBadge->is_visible);
        $this->assertTrue($memberBadge->is_visible);
    }

    public function testEarnedAtCast()
    {
        $member = $this->createMember();
        $badge = $this->createBadge();

        $memberBadge = MemberBadge::create([
            'member_id' => $member->id,
            'badge_id' => $badge->id,
            'earned_at' => now()
        ]);

        $this->assertInstanceOf(\DateTime::class, $memberBadge->earned_at);
    }

    public function testHiddenBadge()
    {
        $member = $this->createMember();
        $badge = $this->createBadge();

        $memberBadge = MemberBadge::create([
            'member_id' => $member->id,
            'badge_id' => $badge->id,
            'earned_at' => now(),
            'is_visible' => false
        ]);

        $this->assertFalse($memberBadge->is_visible);
    }

    public function testUniqueConstraint()
    {
        $member = $this->createMember();
        $badge = $this->createBadge();

        MemberBadge::create([
            'member_id' => $member->id,
            'badge_id' => $badge->id,
            'earned_at' => now()
        ]);

        $this->expectException(\Exception::class);

        // Try to create duplicate
        MemberBadge::create([
            'member_id' => $member->id,
            'badge_id' => $badge->id,
            'earned_at' => now()
        ]);
    }

    public function testTimestamps()
    {
        $member = $this->createMember();
        $badge = $this->createBadge();

        $memberBadge = MemberBadge::create([
            'member_id' => $member->id,
            'badge_id' => $badge->id,
            'earned_at' => now()
        ]);

        $this->assertNotNull($memberBadge->created_at);
        $this->assertNotNull($memberBadge->updated_at);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }
}
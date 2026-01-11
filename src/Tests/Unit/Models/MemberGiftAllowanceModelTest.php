<?php

namespace App\Tests\Unit\Models;

use App\Models\Member;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberGiftAllowanceModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testGetRemainingGiftsReturnsCorrectValue(): void
    {
        $allowance = $this->createMemberGiftAllowance([
            'annual_gift_limit' => 10,
            'gifts_used_this_year' => 3
        ]);

        $remaining = $allowance->getRemainingGifts();

        $this->assertEquals(7, $remaining);
    }

    public function testGetRemainingGiftsReturnsZeroWhenLimitReached(): void
    {
        $allowance = $this->createMemberGiftAllowance([
            'annual_gift_limit' => 10,
            'gifts_used_this_year' => 10
        ]);

        $remaining = $allowance->getRemainingGifts();

        $this->assertEquals(0, $remaining);
    }

    public function testGetRemainingGiftsReturnsZeroWhenOverLimit(): void
    {
        $allowance = $this->createMemberGiftAllowance([
            'annual_gift_limit' => 10,
            'gifts_used_this_year' => 12
        ]);

        $remaining = $allowance->getRemainingGifts();
        $this->assertEquals(0, $remaining);
    }

    public function testCanGiftReturnsTrueWhenGiftsRemaining(): void
    {
        $allowance = $this->createMemberGiftAllowance([
            'annual_gift_limit' => 10,
            'gifts_used_this_year' => 5
        ]);

        $this->assertTrue($allowance->canGift());
    }

    public function testCanGiftReturnsFalseWhenLimitReached(): void
    {
        $allowance = $this->createMemberGiftAllowance([
            'annual_gift_limit' => 10,
            'gifts_used_this_year' => 10
        ]);

        $this->assertFalse($allowance->canGift());
    }

    public function testIncrementUsageIncrementsCounter(): void
    {
        $allowance = $this->createMemberGiftAllowance([
            'annual_gift_limit' => 10,
            'gifts_used_this_year' => 3
        ]);

        $result = $allowance->incrementUsage();

        $this->assertTrue($result);
        $allowance = $allowance->fresh();
        $this->assertEquals(4, $allowance->gifts_used_this_year);
    }

    public function testIncrementUsageFailsWhenLimitReached(): void
    {
        $allowance = $this->createMemberGiftAllowance([
            'annual_gift_limit' => 10,
            'gifts_used_this_year' => 10
        ]);

        $result = $allowance->incrementUsage();

        $this->assertFalse($result);
        $allowance = $allowance->fresh();
        $this->assertEquals(10, $allowance->gifts_used_this_year);
    }

    public function testResetIfNewYearResetsWhenYearPassed(): void
    {
        $oldDate = now_datetime()->modify('-1 year')->modify('-1 day');
        $allowance = $this->createMemberGiftAllowance([
            'annual_gift_limit' => 10,
            'gifts_used_this_year' => 8,
            'year_start_date' => $oldDate->format('Y-m-d')
        ]);

        $allowance->resetIfNewYear();

        $allowance = $allowance->fresh();
        $this->assertEquals(0, $allowance->gifts_used_this_year);
        $this->assertGreaterThan($oldDate->format('Y-m-d'), $allowance->year_start_date);
    }

    public function testResetIfNewYearDoesNotResetWhenYearNotPassed(): void
    {
        $recentDate = now_datetime()->modify('-6 months');
        $allowance = $this->createMemberGiftAllowance([
            'annual_gift_limit' => 10,
            'gifts_used_this_year' => 5,
            'year_start_date' => $recentDate->format('Y-m-d')
        ]);

        $allowance->resetIfNewYear();

        $allowance = $allowance->fresh();
        $this->assertEquals(5, $allowance->gifts_used_this_year);
    }

    public function testMemberRelationship(): void
    {
        $member = $this->createMember();
        $allowance = $this->createMemberGiftAllowance(['member_id' => $member->id]);

        $relatedMember = $allowance->member;

        $this->assertInstanceOf(Member::class, $relatedMember);
        $this->assertEquals($member->id, $relatedMember->id);
    }
}
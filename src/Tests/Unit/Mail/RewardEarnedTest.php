<?php

namespace App\Tests\Unit\Mail;

use App\Mail\Rewards\RewardEarned;
use App\Models\Member;
use App\Models\MemberReward;
use App\Models\RewardDefinition;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class RewardEarnedTest extends FunctionalTestCase
{
    public function testBuildsSetsCorrectSubject(): void
    {
        $member = $this->createMockMember();
        $reward = $this->createMockReward('voucher');

        $mailable = new RewardEarned($member, $reward);
        $mailable->build();

        $this->assertStringContainsString('Earned a Gift Voucher', $mailable->subject);
    }

    private function createMockMember(): Member
    {
        $member = new Member();
        $member->id = 1;
        $member->email = 'test@example.com';
        $member->first_name = 'John';
        $member->last_name = 'Doe';
        return $member;
    }

    private function createMockReward(string $type = 'voucher'): MemberReward
    {
        $definition = new RewardDefinition();
        $definition->id = 1;
        $definition->reward_type = $type;
        $definition->name = 'Test Reward';

        $reward = new MemberReward();
        $reward->id = 1;
        $reward->member_id = 1;
        $reward->reward_definition_id = 1;
        $reward->status = 'pending';
        $reward->reward_data = [
            'voucher_code' => 'GIFT2024',
            'value' => 10.00,
            'currency' => 'GBP',
            'provider' => 'Amazon'
        ];
        $reward->expires_at = date('Y-m-d H:i:s', strtotime('+90 days'));
        $reward->definition = $definition;

        return $reward;
    }

    public function testIncludesRewardData(): void
    {
        $member = $this->createMockMember();
        $reward = $this->createMockReward('voucher');

        $mailable = new RewardEarned($member, $reward);
        $mailable->build();

        $this->assertEquals($reward->reward_data, $mailable->viewData['rewardData']);
        $this->assertArrayHasKey('voucher_code', $mailable->viewData['rewardData']);
    }

    public function testRendersWithMemberData(): void
    {
        $member = $this->createMockMember();
        $reward = $this->createMockReward('voucher');

        $mailable = new RewardEarned($member, $reward);
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString($member->first_name, $html);
        $this->assertStringContainsString('GIFT2024', $html);
    }

    public function testHandlesDiscountRewardType(): void
    {
        $member = $this->createMockMember();
        $reward = $this->createMockReward('discount');
        $reward->reward_data = [
            'discount_type' => 'percentage',
            'discount_value' => 15
        ];

        $mailable = new RewardEarned($member, $reward);
        $mailable->build();

        $this->assertStringContainsString('Discount', $mailable->subject);
    }

    public function testHandlesPointsRewardType(): void
    {
        $member = $this->createMockMember();
        $reward = $this->createMockReward('points');
        $reward->reward_data = [
            'points' => 100
        ];

        $mailable = new RewardEarned($member, $reward);
        $mailable->build();

        $this->assertStringContainsString('Points Reward', $mailable->subject);
    }

    public function testIncludesExpirationDate(): void
    {
        $member = $this->createMockMember();
        $reward = $this->createMockReward('voucher');

        $mailable = new RewardEarned($member, $reward);
        $mailable->build();

        $this->assertEquals($reward->expires_at, $mailable->viewData['expiresAt']);
    }

    public function testUsesMarkdownTemplate(): void
    {
        $member = $this->createMockMember();
        $reward = $this->createMockReward('voucher');

        $mailable = new RewardEarned($member, $reward);
        $mailable->build();

        $this->assertEquals('emails.rewards.earned', $mailable->markdown);
    }
}
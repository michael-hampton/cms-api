<?php

namespace App\Tests\Unit\Mail;

use App\Mail\Rewards\RewardClaimed;
use App\Models\Member;
use App\Models\MemberReward;
use App\Models\RewardDefinition;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class RewardClaimedTest extends FunctionalTestCase
{
    public function testBuildsSetsCorrectSubjectForVoucher(): void
    {
        $member = $this->createMockMember();
        $reward = $this->createMockReward('voucher');

        $mailable = new RewardClaimed($member, $reward);
        $mailable->build();

        $this->assertStringContainsString('Your Voucher is Ready', $mailable->subject);
    }

    private function createMockMember(): Member
    {
        $member = new Member();
        $member->id = 1;
        $member->email = 'rewards@example.com';
        $member->first_name = 'Chris';
        $member->last_name = 'Taylor';
        return $member;
    }

    private function createMockReward(string $type = 'voucher'): MemberReward
    {
        $definition = new RewardDefinition();
        $definition->id = 1;
        $definition->reward_type = $type;
        $definition->name = 'Claimed Reward';

        $reward = new MemberReward();
        $reward->id = 1;
        $reward->member_id = 1;
        $reward->reward_definition_id = 1;
        $reward->status = 'claimed';

        if ($type === 'voucher') {
            $reward->reward_data = [
                'voucher_code' => 'CLAIMED2024',
                'value' => 50.00,
                'currency' => 'GBP',
                'provider' => 'Amazon'
            ];
        } elseif ($type === 'discount') {
            $reward->reward_data = [
                'discount_type' => 'percentage',
                'discount_value' => 20
            ];
        } else {
            $reward->reward_data = [
                'points' => 500
            ];
        }

        $reward->claimed_at = date('Y-m-d H:i:s');
        $reward->definition = $definition;

        return $reward;
    }

    public function testBuildsSetsCorrectSubjectForDiscount(): void
    {
        $member = $this->createMockMember();
        $reward = $this->createMockReward('discount');

        $mailable = new RewardClaimed($member, $reward);
        $mailable->build();

        $this->assertStringContainsString('Your Discount Code is Ready', $mailable->subject);
    }

    public function testBuildsSetsCorrectSubjectForPoints(): void
    {
        $member = $this->createMockMember();
        $reward = $this->createMockReward('points');

        $mailable = new RewardClaimed($member, $reward);
        $mailable->build();

        $this->assertStringContainsString('Your Points is Ready', $mailable->subject);
    }

    public function testIncludesRewardData(): void
    {
        $member = $this->createMockMember();
        $reward = $this->createMockReward('voucher');

        $mailable = new RewardClaimed($member, $reward);
        $mailable->build();

        $this->assertEquals($reward->reward_data, $mailable->viewData['rewardData']);
        $this->assertArrayHasKey('voucher_code', $mailable->viewData['rewardData']);
    }

    public function testRendersWithVoucherCode(): void
    {
        $member = $this->createMockMember();
        $reward = $this->createMockReward('voucher');

        $mailable = new RewardClaimed($member, $reward);
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('CLAIMED2024', $html);
        $this->assertStringContainsString($member->first_name, $html);
    }

    public function testIncludesClaimedAt(): void
    {
        $member = $this->createMockMember();
        $reward = $this->createMockReward('voucher');

        $mailable = new RewardClaimed($member, $reward);
        $mailable->build();

        $this->assertEquals($reward->claimed_at, $mailable->viewData['claimedAt']);
    }

    public function testUsesMarkdownTemplate(): void
    {
        $member = $this->createMockMember();
        $reward = $this->createMockReward('voucher');

        $mailable = new RewardClaimed($member, $reward);
        $mailable->build();

        $this->assertEquals('emails.rewards.claimed', $mailable->markdown);
    }
}
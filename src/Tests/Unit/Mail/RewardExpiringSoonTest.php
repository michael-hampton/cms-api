<?php

namespace App\Tests\Unit\Mail;

use App\Mail\RewardExpiringSoon;
use App\Models\Member;
use App\Models\MemberReward;
use App\Models\RewardDefinition;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class RewardExpiringSoonTest extends FunctionalTestCase
{
    public function testBuildsSetsCorrectSubject(): void
    {
        $member = $this->createMockMember();
        $reward = $this->createMockReward();

        $mailable = new RewardExpiringSoon($member, $reward, 3);
        $mailable->build();

        $this->assertStringContainsString('Reward Expires Soon', $mailable->subject);
    }

    private function createMockMember(): Member
    {
        $member = new Member();
        $member->id = 1;
        $member->email = 'test@example.com';
        $member->first_name = 'Jane';
        $member->last_name = 'Smith';
        return $member;
    }

    private function createMockReward(): MemberReward
    {
        $definition = new RewardDefinition();
        $definition->id = 1;
        $definition->reward_type = 'voucher';
        $definition->name = 'Expiring Reward';

        $reward = new MemberReward();
        $reward->id = 1;
        $reward->member_id = 1;
        $reward->reward_definition_id = 1;
        $reward->status = 'pending';
        $reward->reward_data = [
            'voucher_code' => 'EXPIRING123',
            'value' => 25.00,
            'currency' => 'GBP'
        ];
        $reward->expires_at = date('Y-m-d H:i:s', strtotime('+3 days'));
        $reward->definition = $definition;

        return $reward;
    }

    public function testIncludesDaysUntilExpiry(): void
    {
        $member = $this->createMockMember();
        $reward = $this->createMockReward();
        $daysUntilExpiry = 3;

        $mailable = new RewardExpiringSoon($member, $reward, $daysUntilExpiry);
        $mailable->build();

        $this->assertEquals($daysUntilExpiry, $mailable->viewData['daysUntilExpiry']);
    }

    public function testRendersWithUrgency(): void
    {
        $member = $this->createMockMember();
        $reward = $this->createMockReward();

        $mailable = new RewardExpiringSoon($member, $reward, 1);
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString($member->first_name, $html);
        $this->assertStringContainsString('EXPIRING123', $html);
    }

    public function testIncludesRewardData(): void
    {
        $member = $this->createMockMember();
        $reward = $this->createMockReward();

        $mailable = new RewardExpiringSoon($member, $reward, 2);
        $mailable->build();

        $this->assertEquals($reward->reward_data, $mailable->viewData['rewardData']);
    }
}
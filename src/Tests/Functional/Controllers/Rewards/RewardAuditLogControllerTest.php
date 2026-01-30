<?php

namespace App\Tests\Functional\Controllers\Rewards;

use App\Models\RewardAuditLog;
use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class RewardAuditLogControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private User $user;

    public function testIndexReturnsRecentLogs(): void
    {
        $this->actingAs($this->user);

        $reward = $this->createMemberReward();

        RewardAuditLog::create([
            'member_reward_id' => $reward->id,
            'action' => 'created',
            'new_status' => 'pending',
        ]);

        RewardAuditLog::create([
            'member_reward_id' => $reward->id,
            'action' => 'updated',
            'old_status' => 'pending',
            'new_status' => 'claimed',
        ]);

        $response = $this->getForSite('/api/reward-audit-logs');
        $data = json_decode($response->getContent(), true);

        $this->assertResponseOk($response);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('logs', $data);
        $this->assertGreaterThanOrEqual(2, count($data['logs']));
    }

    public function testIndexSupportsLimit(): void
    {
        $this->actingAs($this->user);

        $reward = $this->createMemberReward();

        for ($i = 0; $i < 100; $i++) {
            RewardAuditLog::create([
                'member_reward_id' => $reward->id,
                'action' => 'viewed',
                'new_status' => 'pending',
            ]);
        }

        $response = $this->getForSite('/api/reward-audit-logs?limit=10');
        $data = json_decode($response->getContent(), true);

        $this->assertResponseOk($response);
        $this->assertCount(10, $data['logs']);
    }

    public function testGetForReward(): void
    {
        $this->actingAs($this->user);

        $reward1 = $this->createMemberReward();
        $reward2 = $this->createMemberReward();

        RewardAuditLog::create([
            'member_reward_id' => $reward1->id,
            'action' => 'created',
            'new_status' => 'pending',
        ]);

        RewardAuditLog::create([
            'member_reward_id' => $reward1->id,
            'action' => 'claimed',
            'new_status' => 'claimed',
        ]);

        RewardAuditLog::create([
            'member_reward_id' => $reward2->id,
            'action' => 'created',
            'new_status' => 'pending',
        ]);

        $response = $this->getForSite("/api/reward-audit-logs/reward/{$reward1->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertResponseOk($response);
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['logs']);
    }

    public function testGetByAction(): void
    {
        $this->actingAs($this->user);

        $reward = $this->createMemberReward();

        RewardAuditLog::create([
            'member_reward_id' => $reward->id,
            'action' => 'claimed',
            'new_status' => 'claimed',
        ]);

        RewardAuditLog::create([
            'member_reward_id' => $reward->id,
            'action' => 'claimed',
            'new_status' => 'claimed',
        ]);

        RewardAuditLog::create([
            'member_reward_id' => $reward->id,
            'action' => 'declined',
            'new_status' => 'declined',
        ]);

        $response = $this->getForSite('/api/reward-audit-logs/action/claimed');
        $data = json_decode($response->getContent(), true);

        $this->assertResponseOk($response);
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['logs']);
    }

    public function testGetByDateRangeRequiresDateRange(): void
    {
        $this->actingAs($this->user);

        $response = $this->getForSite('/api/reward-audit-logs/date-range');

        $this->assertResponseStatus(422, $response);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
    }
}
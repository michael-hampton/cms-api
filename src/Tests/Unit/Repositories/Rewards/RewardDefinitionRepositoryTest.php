<?php

namespace App\Tests\Unit\Repositories\Rewards;

use App\Models\RewardAuditLog;
use App\Models\RewardDefinition;
use App\Repositories\Rewards\RewardAuditLogRepository;
use App\Repositories\Rewards\RewardDefinitionRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class RewardDefinitionRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private RewardDefinitionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new RewardDefinitionRepository(new RewardAuditLogRepository());
    }

    public function testFindRewardDefinitionById(): void
    {
        $definition = $this->createRewardDefinition([
            'name' => 'Test Reward',
            'reward_type' => 'points',
        ]);

        $found = $this->repository->findRewardDefinitionById($definition->id);

        $this->assertNotNull($found);
        $this->assertEquals($definition->id, $found->id);
        $this->assertEquals('Test Reward', $found->name);
    }

    public function testFindRewardDefinitionByIdReturnsNull(): void
    {
        $found = $this->repository->findRewardDefinitionById(9999);

        $this->assertNull($found);
    }

    public function testSearchRewardDefinitions(): void
    {
        $site = $this->createSite();

        $this->createRewardDefinition([
            'site_id' => $site->id,
            'name' => 'Welcome Reward',
            'is_active' => true,
        ]);

        $this->createRewardDefinition([
            'site_id' => $site->id,
            'name' => 'Loyalty Reward',
            'is_active' => false,
        ]);

        $result = $this->repository->searchRewardDefinitions($site->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(2, $result['total']);
        $this->assertCount(2, $result['data']);
    }

    public function testSearchRewardDefinitionsWithFilters(): void
    {
        $site = $this->createSite();

        $this->createRewardDefinition([
            'site_id' => $site->id,
            'name' => 'Welcome Reward',
            'reward_type' => 'points',
            'is_active' => true,
        ]);

        $this->createRewardDefinition([
            'site_id' => $site->id,
            'name' => 'Loyalty Reward',
            'reward_type' => 'voucher',
            'is_active' => false,
        ]);

        // Filter by active
        $result = $this->repository->searchRewardDefinitions($site->id, ['is_active' => true]);
        $this->assertEquals(1, $result['total']);

        // Filter by reward type
        $result = $this->repository->searchRewardDefinitions($site->id, ['reward_type' => 'voucher']);
        $this->assertEquals(1, $result['total']);
        $this->assertEquals('voucher', $result['data']->first()->reward_type);

        // Search by name
        $result = $this->repository->searchRewardDefinitions($site->id, ['search' => 'Welcome']);
        $this->assertEquals(1, $result['total']);
        $this->assertEquals('Welcome Reward', $result['data']->first()->name);
    }

    public function testSearchRewardDefinitionsWithPagination(): void
    {
        $site = $this->createSite();

        for ($i = 1; $i <= 25; $i++) {
            $this->createRewardDefinition([
                'site_id' => $site->id,
                'name' => "Reward $i",
            ]);
        }

        $result = $this->repository->searchRewardDefinitions($site->id, [], 1, 10);

        $this->assertEquals(25, $result['total']);
        $this->assertCount(10, $result['data']);
        $this->assertEquals(1, $result['page']);
        $this->assertEquals(10, $result['per_page']);
        $this->assertEquals(3, $result['last_page']);
    }

    public function testSearchRewardDefinitionsWithSorting(): void
    {
        $site = $this->createSite();

        $this->createRewardDefinition([
            'site_id' => $site->id,
            'name' => 'Zebra Reward',
            'sort_order' => 2,
        ]);

        $this->createRewardDefinition([
            'site_id' => $site->id,
            'name' => 'Alpha Reward',
            'sort_order' => 1,
        ]);

        // Default sorting by sort_order
        $result = $this->repository->searchRewardDefinitions($site->id);
        $this->assertEquals('Alpha Reward', $result['data']->first()->name);

        // Sort by name ascending
        $result = $this->repository->searchRewardDefinitions($site->id, [
            'sort_by' => 'name',
            'sort_order' => 'asc',
        ]);
        $this->assertEquals('Alpha Reward', $result['data']->first()->name);

        // Sort by name descending
        $result = $this->repository->searchRewardDefinitions($site->id, [
            'sort_by' => 'name',
            'sort_order' => 'desc',
        ]);
        $this->assertEquals('Zebra Reward', $result['data']->first()->name);
    }

    public function testGetRewardDefinitionStats(): void
    {
        $site = $this->createSite();
        $member = $this->createMember();

        $definition1 = $this->createRewardDefinition([
            'site_id' => $site->id,
            'reward_type' => 'points',
            'is_active' => true,
        ]);

        $definition2 = $this->createRewardDefinition([
            'site_id' => $site->id,
            'reward_type' => 'voucher',
            'is_active' => false,
        ]);

        $this->createMemberReward([
            'member_id' => $member->id,
            'reward_definition_id' => $definition1->id,
            'status' => 'claimed',
        ]);

        $this->createMemberReward([
            'member_id' => $member->id,
            'reward_definition_id' => $definition2->id,
            'status' => 'pending',
        ]);

        $stats = $this->repository->getRewardDefinitionStats($site->id);

        $this->assertIsArray($stats);
        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(1, $stats['active']);
        $this->assertEquals(1, $stats['inactive']);
        $this->assertArrayHasKey('by_type', $stats);
        $this->assertEquals(1, $stats['by_type']['points']);
        $this->assertEquals(1, $stats['by_type']['voucher']);
    }

    public function testGetRewardDefinitionStatsWithClickData(): void
    {
        $site = $this->createSite();
        $member = $this->createMember();

        $definition = $this->createRewardDefinition([
            'site_id' => $site->id,
        ]);

        $reward = $this->createMemberReward([
            'member_id' => $member->id,
            'reward_definition_id' => $definition->id,
            'status' => 'claimed',
        ]);

        // Create reward clicks
        $this->database->table('reward_clicks')->insert([
            'member_reward_id' => $reward->id,
            'member_id' => $member->id,
            'site_id' => $site->id,
            'action' => 'view',
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        $this->database->table('reward_clicks')->insert([
            'member_reward_id' => $reward->id,
            'member_id' => $member->id,
            'site_id' => $site->id,
            'action' => 'claim',
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        $stats = $this->repository->getRewardDefinitionStats($site->id);

        $this->assertEquals(2, $stats['total_clicks']);
        $this->assertEquals(1, $stats['unique_clickers']);
        $this->assertArrayHasKey('clicks_by_action', $stats);
        $this->assertArrayHasKey('recent_clicks', $stats);
    }

    public function testFindBySite(): void
    {
        $site1 = $this->createSite();
        $site2 = $this->createSite();

        $this->createRewardDefinition(['site_id' => $site1->id]);
        $this->createRewardDefinition(['site_id' => $site1->id]);
        $this->createRewardDefinition(['site_id' => $site2->id]);

        $definitions = $this->repository->findBySite($site1->id);

        $this->assertCount(2, $definitions);
        foreach ($definitions as $definition) {
            $this->assertEquals($site1->id, $definition->site_id);
        }
    }

    public function testCreateRewardDefinition(): void
    {
        $site = $this->createSite();

        $data = [
            'site_id' => $site->id,
            'name' => 'New Reward',
            'slug' => 'new-reward',
            'description' => 'Test description',
            'reward_type' => 'points',
            'criteria' => [
                ['type' => 'signup', 'operator' => '>=', 'value' => 1]
            ],
            'reward_config' => [
                'points' => 100
            ],
            'max_claims_per_member' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ];

        $definition = $this->repository->create($data);

        $this->assertNotNull($definition);
        $this->assertEquals('New Reward', $definition->name);
        $this->assertEquals('points', $definition->reward_type);
        $this->assertTrue($definition->is_active);
    }

    public function testUpdateRewardDefinition(): void
    {
        $definition = $this->createRewardDefinition([
            'name' => 'Original Name',
            'is_active' => true,
        ]);

        $updated = $this->repository->update($definition->id, [
            'name' => 'Updated Name',
            'is_active' => false,
        ]);

        $this->assertInstanceOf(RewardDefinition::class, $updated);

        $definition = $definition->fresh();
        $this->assertEquals('Updated Name', $definition->name);
        $this->assertFalse($definition->is_active);
    }

    public function testDeleteRewardDefinition(): void
    {
        $definition = $this->createRewardDefinition();

        $deleted = $this->repository->delete($definition->id);

        $this->assertTrue($deleted);

        $definition = RewardDefinition::find($definition->id);
        $this->assertNull($definition);
    }

    public function testCreateRewardDefinitionLogsAudit(): void
    {
        $site = $this->createSite();
        $user = $this->createUser();

        $data = [
            'site_id' => $site->id,
            'name' => 'New Reward',
            'slug' => 'new-reward',
            'description' => 'Test description',
            'reward_type' => 'points',
            'criteria' => [
                ['type' => 'signup', 'operator' => '>=', 'value' => 1]
            ],
            'reward_config' => [
                'points' => 100
            ],
            'max_claims_per_member' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ];

        $definition = $this->repository->create($data, $user->id);

        $this->assertNotNull($definition);

        // Check audit log was created
        $this->assertDatabaseHas('reward_audit_logs', [
            'reward_definition_id' => $definition->id,
            'user_id' => $user->id,
            'action' => 'definition_created',
            'new_status' => 'active'
        ]);
    }

    public function testUpdateRewardDefinitionLogsAudit(): void
    {
        $user = $this->createUser();
        $definition = $this->createRewardDefinition([
            'name' => 'Original Name',
            'is_active' => true,
        ]);

        $updated = $this->repository->update($definition->id, [
            'name' => 'Updated Name',
            'is_active' => false,
        ], $user->id);

        $this->assertInstanceOf(RewardDefinition::class, $updated);

        // Check audit log was created
        $this->assertDatabaseHas('reward_audit_logs', [
            'reward_definition_id' => $definition->id,
            'user_id' => $user->id,
            'action' => 'definition_updated',
            'old_status' => 'active',
            'new_status' => 'inactive'
        ]);
    }

    public function testDeleteRewardDefinitionLogsAudit(): void
    {
        $user = $this->createUser();
        $definition = $this->createRewardDefinition([
            'is_active' => true
        ]);

        $deleted = $this->repository->delete($definition->id, $user->id);

        $this->assertTrue($deleted);

        // Check audit log was created
        $this->assertDatabaseHas('reward_audit_logs', [
            'reward_definition_id' => $definition->id,
            'user_id' => $user->id,
            'action' => 'definition_deleted',
            'old_status' => 'active'
        ]);
    }

    public function testGetAuditLogsForDefinition(): void
    {
        $user = $this->createUser();
        $definition = $this->createRewardDefinition();
        $member = $this->createMember();

        // Create
        $this->repository->create([
            'site_id' => $definition->site_id,
            'name' => 'Test',
            'reward_type' => 'points',
            'member_id' => $member->id,
            'criteria' => [],
            'reward_config' => [],
            'reward_definition_id' => $definition->id,
            'earned_at' => now_datetime(),
            'slug' => 'test',
            'is_active' => true
        ], $user->id);

        // Update
        $this->repository->update($definition->id, [
            'name' => 'Updated'
        ], $user->id);

        $logs = RewardAuditLog::where('reward_definition_id', $definition->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $this->assertGreaterThan(0, $logs->count());
        $this->assertEquals('definition_updated', $logs->first()->action);
    }
}
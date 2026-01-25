<?php

namespace App\Tests\Functional\Controllers\Rewards;

use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class RewardDefinitionsAdminControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private User $user;

    public function testIndexRequiresAuth(): void
    {
        $response = $this->getForSite('/api/reward-definitions');

        $this->assertResponseStatus(302, $response);
    }

    public function testSearchReturnsDefinitionsAndStats(): void
    {
        $this->actingAs($this->user);

        $definition1 = $this->createRewardDefinition([
            'name' => 'Welcome Reward',
            'is_active' => true
        ]);

        $definition2 = $this->createRewardDefinition([
            'name' => 'Loyalty Reward',
            'is_active' => false
        ]);

        $response = $this->getForSite('/api/reward-definitions/search');

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('definitions', $data);
        $this->assertArrayHasKey('stats', $data);
        $this->assertGreaterThanOrEqual(2, $data['stats']['total']);
    }

    public function testSearchFiltersByActiveStatus(): void
    {
        $this->actingAs($this->user);

        $this->createRewardDefinition(['is_active' => true]);
        $this->createRewardDefinition(['is_active' => false]);

        $response = $this->getForSite('/api/reward-definitions/search?is_active=1');

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        foreach ($data['definitions']['data'] as $definition) {
            $this->assertTrue($definition['is_active']);
        }
    }

    public function testSearchFiltersByRewardType(): void
    {
        $this->actingAs($this->user);

        $this->createRewardDefinition(['reward_type' => 'voucher']);
        $this->createRewardDefinition(['reward_type' => 'points']);

        $response = $this->getForSite('/api/reward-definitions/search?reward_type=voucher');

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        foreach ($data['definitions']['data'] as $definition) {
            $this->assertEquals('voucher', $definition['reward_type']);
        }
    }

    public function testSearchSupportsSearchQuery(): void
    {
        $this->actingAs($this->user);

        $this->createRewardDefinition(['name' => 'Welcome Bonus']);
        $this->createRewardDefinition(['name' => 'Loyalty Points']);

        $response = $this->getForSite('/api/reward-definitions/search?search=Welcome');

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['definitions']['data']);
        $this->assertEquals('Welcome Bonus', $data['definitions']['data'][0]['name']);
    }

    public function testSearchSupportsSorting(): void
    {
        $this->actingAs($this->user);

        $this->createRewardDefinition(['name' => 'Zebra Reward']);
        $this->createRewardDefinition(['name' => 'Alpha Reward']);

        $response = $this->getForSite('/api/reward-definitions/search?sort_by=name&sort_order=asc');

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Alpha Reward', $data['definitions']['data'][0]['name']);
    }

    public function testSearchSupportsPagination(): void
    {
        $this->actingAs($this->user);

        for ($i = 1; $i <= 25; $i++) {
            $this->createRewardDefinition(['name' => "Reward $i"]);
        }

        $response = $this->getForSite('/api/reward-definitions/search?page=1&per_page=10');

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(10, $data['definitions']['data']);
        $this->assertEquals(1, $data['definitions']['pagination']['current_page']);
    }

    public function testShowReturnsDefinitionDetails(): void
    {
        $this->actingAs($this->user);

        $definition = $this->createRewardDefinition([
            'name' => 'Test Reward',
            'description' => 'Test description'
        ]);

        $response = $this->getForSite("/api/reward-definitions/{$definition->id}");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals($definition->id, $data['definition']['id']);
        $this->assertEquals('Test Reward', $data['definition']['name']);
    }

    public function testCreateRewardDefinition(): void
    {
        $this->actingAs($this->user);

        $payload = [
            'name' => 'New Reward',
            'slug' => 'new-reward',
            'description' => 'Test reward',
            'reward_type' => 'points',
            'criteria' => [
                ['type' => 'signup', 'operator' => '>=', 'value' => 1]
            ],
            'reward_config' => [
                'points' => 100
            ],
            'max_claims_per_member' => 1,
            'is_active' => true,
            'sort_order' => 1
        ];

        $response = $this->postForSite('/api/reward-definitions', $payload);

        $this->assertResponseStatus(201, $response);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('New Reward', $data['definition']['name']);
    }

    public function testCreateRequiresValidation(): void
    {
        $this->actingAs($this->user);

        $response = $this->postForSite('/api/reward-definitions', []);

        $this->assertResponseStatus(422, $response);
    }

    public function testUpdateRewardDefinition(): void
    {
        $this->actingAs($this->user);

        $definition = $this->createRewardDefinition([
            'name' => 'Original Name',
            'is_active' => true
        ]);

        $response = $this->putForSite(
            "/api/reward-definitions/{$definition->id}",
            [
                'name' => 'Updated Name',
                'is_active' => false
            ]
        );

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('Updated Name', $data['definition']['name']);
        $this->assertFalse($data['definition']['is_active']);
    }

    public function testDeleteRewardDefinition(): void
    {
        $this->actingAs($this->user);

        $definition = $this->createRewardDefinition();

        $response = $this->deleteForSite("/api/reward-definitions/{$definition->id}");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
    }

    public function testCannotDeleteDefinitionWithMemberRewards(): void
    {
        $this->actingAs($this->user);

        $member = $this->createMember();
        $definition = $this->createRewardDefinition();

        $this->createMemberReward([
            'member_id' => $member->id,
            'reward_definition_id' => $definition->id
        ]);

        $response = $this->deleteForSite("/api/reward-definitions/{$definition->id}");

        $this->assertResponseStatus(400, $response);
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('existing member rewards', $data['message']);
    }

    public function testSearchFiltersByMultipleRewardTypes(): void
    {
        $this->actingAs($this->user);

        $this->createRewardDefinition(['reward_type' => 'voucher']);
        $this->createRewardDefinition(['reward_type' => 'points']);
        $this->createRewardDefinition(['reward_type' => 'discount']);

        $response = $this->getForSite('/api/reward-definitions/search?reward_type=voucher,points');

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertGreaterThanOrEqual(2, count($data['definitions']['data']));

        foreach ($data['definitions']['data'] as $definition) {
            $this->assertContains($definition['reward_type'], ['voucher', 'points']);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
    }
}
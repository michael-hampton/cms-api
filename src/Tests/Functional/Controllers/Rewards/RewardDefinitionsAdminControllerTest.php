<?php

namespace App\Tests\Functional\Controllers\Rewards;

use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use PHPUnit\Framework\Attributes\DataProvider;

class RewardDefinitionsAdminControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private User $user;

    public function testIndexRequiresAuth(): void
    {
        $response = $this->getForSite('/api/reward-definitions');

        $this->assertResponseStatus(302, $response);
    }

//    public function testSearchReturnsDefinitionsAndStats(): void
//    {
//        $this->actingAs($this->user);
//
//        $definition1 = $this->createRewardDefinition([
//            'name' => 'Welcome Reward',
//            'is_active' => true
//        ]);
//
//        $definition2 = $this->createRewardDefinition([
//            'name' => 'Loyalty Reward',
//            'is_active' => false
//        ]);
//
//        $response = $this->getForSite('/api/reward-definitions/search');
//
//        $this->assertResponseOk($response);
//        $data = json_decode($response->getContent(), true);
//
//        $this->assertTrue($data['success']);
//        $this->assertArrayHasKey('definitions', $data);
//        $this->assertArrayHasKey('stats', $data);
//        $this->assertGreaterThanOrEqual(2, $data['stats']['total']);
//    }

    public function testSearchFiltersByActiveStatus(): void
    {
        $this->actingAs($this->user);

        $this->createRewardDefinition(['is_active' => true]);
        $this->createRewardDefinition(['is_active' => false]);

        $response = $this->getForSite('/api/reward-definitions/search?is_active=1');

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        foreach ($data['definitions']['items'] as $definition) {
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

        foreach ($data['definitions']['items'] as $definition) {
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
        $this->assertCount(1, $data['definitions']['items']);
        $this->assertEquals('Welcome Bonus', $data['definitions']['items'][0]['name']);
    }

    public function testSearchSupportsSorting(): void
    {
        $this->actingAs($this->user);

        $this->createRewardDefinition(['name' => 'Zebra Reward']);
        $this->createRewardDefinition(['name' => 'Alpha Reward']);

        $response = $this->getForSite('/api/reward-definitions/search?sort_by=name&sort_order=asc');

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Alpha Reward', $data['definitions']['items'][0]['name']);
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
        $this->assertCount(10, $data['definitions']['items']);
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

    public function testCreateRequiresName(): void
    {
        $this->actingAs($this->user);

        $response = $this->postForSite(
            '/api/reward-definitions',
            $this->validCreatePayload(['name' => null])
        );

        $this->assertResponseStatus(422, $response);
    }

    public function testCreateRequiresSlug(): void
    {
        $this->actingAs($this->user);

        $response = $this->postForSite(
            '/api/reward-definitions',
            $this->validCreatePayload(['slug' => null])
        );

        $this->assertResponseStatus(422, $response);
    }

    public function testCreateRequiresRewardType(): void
    {
        $this->actingAs($this->user);

        $response = $this->postForSite(
            '/api/reward-definitions',
            $this->validCreatePayload(['reward_type' => null])
        );

        $this->assertResponseStatus(422, $response);
    }

    public function testCreateRejectsRewardTypeNotInAllowedValues(): void
    {
        $this->actingAs($this->user);

        $response = $this->postForSite(
            '/api/reward-definitions',
            $this->validCreatePayload(['reward_type' => 'cashback']) // not in enum
        );

        $this->assertResponseStatus(422, $response);
    }

    #[DataProvider('validRewardTypes')]
    public function testCreateAcceptsAllValidRewardTypes(string $type): void
    {
        $this->actingAs($this->user);

        $response = $this->postForSite(
            '/api/reward-definitions',
            $this->validCreatePayload([
                'reward_type' => $type,
                'slug' => "reward-{$type}",
                'reward_config' => $type === 'voucher'
                    ? ['code_prefix' => 'VCH']
                    : ($type === 'discount' ? ['percentage' => 10] : ['points' => 100]),
            ])
        );

        $this->assertResponseStatus(201, $response);
    }

    public static function validRewardTypes(): array
    {
        return [
            ['voucher'],
            ['discount'],
            ['points'],
        ];
    }

    public function testCreateRequiresValidation(): void
    {
        $this->actingAs($this->user);

        $response = $this->postForSite('/api/reward-definitions', []);

        $this->assertResponseStatus(422, $response);
    }

    public function testCreateRequiresCriteria(): void
    {
        $this->actingAs($this->user);

        $response = $this->postForSite(
            '/api/reward-definitions',
            $this->validCreatePayload(['criteria' => null])
        );

        $this->assertResponseStatus(422, $response);
    }

    public function testCreateRejectsCriteriaThatIsNotAnArray(): void
    {
        $this->actingAs($this->user);

        $response = $this->postForSite(
            '/api/reward-definitions',
            $this->validCreatePayload(['criteria' => 'not-an-array'])
        );

        $this->assertResponseStatus(422, $response);
    }

    public function testCreateRequiresRewardConfig(): void
    {
        $this->actingAs($this->user);

        $response = $this->postForSite(
            '/api/reward-definitions',
            $this->validCreatePayload(['reward_config' => null])
        );

        $this->assertResponseStatus(422, $response);
    }

    public function testCreateRejectsRewardConfigThatIsNotAnArray(): void
    {
        $this->actingAs($this->user);

        $response = $this->postForSite(
            '/api/reward-definitions',
            $this->validCreatePayload(['reward_config' => 'not-an-array'])
        );

        $this->assertResponseStatus(422, $response);
    }

    public function testCreateRejectsMaxClaimsPerMemberBelow1(): void
    {
        $this->actingAs($this->user);

        $response = $this->postForSite(
            '/api/reward-definitions',
            $this->validCreatePayload(['max_claims_per_member' => 0])
        );

        $this->assertResponseStatus(422, $response);
    }

    public function testCreateRejectsNameExceeding255Characters(): void
    {
        $this->actingAs($this->user);

        $response = $this->postForSite(
            '/api/reward-definitions',
            $this->validCreatePayload(['name' => str_repeat('x', 256)])
        );

        $this->assertResponseStatus(422, $response);
    }

    public function testCreateRejectsSlugExceeding255Characters(): void
    {
        $this->actingAs($this->user);

        $response = $this->postForSite(
            '/api/reward-definitions',
            $this->validCreatePayload(['slug' => str_repeat('x', 256)])
        );

        $this->assertResponseStatus(422, $response);
    }

    public function testCreateValidatesEmptyPayload(): void
    {
        $this->actingAs($this->user);

        $response = $this->postForSite('/api/reward-definitions', []);

        $this->assertResponseStatus(422, $response);
    }

    public function testUpdateRejectsRewardTypeNotInAllowedValues(): void
    {
        $this->actingAs($this->user);

        $definition = $this->createRewardDefinition();

        $response = $this->putForSite(
            "/api/reward-definitions/{$definition->id}",
            ['reward_type' => 'cashback']
        );

        $this->assertResponseStatus(422, $response);
    }

    public function testUpdateRejectsCriteriaThatIsNotAnArray(): void
    {
        $this->actingAs($this->user);

        $definition = $this->createRewardDefinition();

        $response = $this->putForSite(
            "/api/reward-definitions/{$definition->id}",
            ['criteria' => 'not-an-array']
        );

        $this->assertResponseStatus(422, $response);
    }

    public function testUpdateRejectsRewardConfigThatIsNotAnArray(): void
    {
        $this->actingAs($this->user);

        $definition = $this->createRewardDefinition();

        $response = $this->putForSite(
            "/api/reward-definitions/{$definition->id}",
            ['reward_config' => 'not-an-array']
        );

        $this->assertResponseStatus(422, $response);
    }

    public function testUpdateRejectsMaxClaimsPerMemberBelow1(): void
    {
        $this->actingAs($this->user);

        $definition = $this->createRewardDefinition();

        $response = $this->putForSite(
            "/api/reward-definitions/{$definition->id}",
            ['max_claims_per_member' => 0]
        );

        $this->assertResponseStatus(422, $response);
    }

    public function testUpdateRejectsNameExceeding255Characters(): void
    {
        $this->actingAs($this->user);

        $definition = $this->createRewardDefinition();

        $response = $this->putForSite(
            "/api/reward-definitions/{$definition->id}",
            ['name' => str_repeat('x', 256)]
        );

        $this->assertResponseStatus(422, $response);
    }

    public function testUpdateAcceptsPartialPayload(): void
    {
        $this->actingAs($this->user);

        $definition = $this->createRewardDefinition(['name' => 'Original']);

        // UpdateRewardRequest has no required fields — partial update is valid
        $response = $this->putForSite(
            "/api/reward-definitions/{$definition->id}",
            ['is_active' => false]
        );

        $this->assertResponseOk($response);
        $this->assertFalse($definition->fresh()->is_active);
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

    public function testUpdateReturns404ForNonExistentDefinition(): void
    {
        $this->actingAs($this->user);

        $response = $this->putForSite('/api/reward-definitions/99999', ['name' => 'Ghost']);

        $this->assertResponseStatus(404, $response);
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
        $this->assertGreaterThanOrEqual(2, count($data['definitions']['items']));

        foreach ($data['definitions']['items'] as $definition) {
            $this->assertContains($definition['reward_type'], ['voucher', 'points']);
        }
    }

    public function testGetStatisticsReturnsStats(): void
    {
        $this->actingAs($this->user);

        $this->createRewardDefinition(['is_active' => true]);
        $this->createRewardDefinition(['is_active' => false]);

        $response = $this->getForSite('/api/reward-definitions/statistics');

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('stats', $data);
        $this->assertArrayHasKey('total', $data['stats']);
        $this->assertArrayHasKey('active', $data['stats']);
        $this->assertArrayHasKey('inactive', $data['stats']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
    }

    private function validCreatePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Reward',
            'slug' => 'new-reward',
            'description' => 'Test reward',
            'reward_type' => 'points',
            'criteria' => [
                ['type' => 'signup', 'operator' => '>=', 'value' => 1],
            ],
            'reward_config' => [
                'points' => 100,
            ],
            'max_claims_per_member' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ], $overrides);
    }
}
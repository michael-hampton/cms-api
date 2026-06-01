<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Framework\Container;
use App\Framework\Support\Config;
use App\Models\ContributorProfile;
use App\Models\Contract;
use App\Models\OpenCollabPermission;
use App\Models\OpenCollabSiteUserPermission;
use App\Models\Site;
use App\Models\User;
use App\Repositories\OpenCollab\RbacRepository;
use App\Services\OpenCollab\ContributorPaymentMethodService;
use App\Services\OpenCollab\RbacBootstrapper;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class OnboardingControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private User $contributor;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('rbac', require __DIR__ . '/../../../../config/rbac.php');
        Config::set('rbac.site_enabled', true);
        (new RbacBootstrapper(new RbacRepository()))->ensureSeeded($this->siteId);

        $this->contributor = $this->authenticatedUser;
        $this->contributor->update([
            'role' => 'user',
            'is_contributor' => true,
        ]);
    }

    public function test_status_is_forbidden_without_onboarding_permission(): void
    {
        $response = $this->getForSite('/api/open-collab/onboarding/status');

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_status_returns_pending_steps_for_creator_role(): void
    {
        $user = $this->createAuthorizedOnboardingUser('onboarding.view');
        $this->actingAs($user);

        $response = $this->getForSite('/api/open-collab/onboarding/status');
        $payload = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('pending_steps', $payload['data']);
    }

    public function test_get_contract_returns_latest_contract_for_authorized_user(): void
    {
        $user = $this->createAuthorizedOnboardingUser('contract.sign');
        $this->actingAs($user);

        $contract = Contract::create([
            'site_id' => $this->siteId,
            'version' => 2,
            'content' => str_repeat('contract clause ', 8),
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'published_by' => $user->id,
        ]);

        $response = $this->getForSite('/api/open-collab/onboarding/contract');
        $payload = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($contract->id, $payload['data']['id']);
        $this->assertEquals(2, $payload['data']['version']);
    }

    public function test_sign_contract_records_signature_for_authorized_user(): void
    {
        $user = $this->createAuthorizedOnboardingUser('contract.sign');
        $this->actingAs($user);

        $contract = Contract::create([
            'site_id' => $this->siteId,
            'version' => 3,
            'content' => str_repeat('contract clause ', 8),
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'published_by' => $user->id,
        ]);

        $response = $this->postForSite('/api/open-collab/onboarding/contract', [
            'contract_id' => $contract->id,
            'agreed' => true,
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('oc_user_contract_signatures', [
            'user_id' => $user->id,
            'contract_id' => $contract->id,
        ]);
    }

    public function test_acknowledge_guidelines_records_acknowledgement_for_authorized_user(): void
    {
        $user = $this->createAuthorizedOnboardingUser('guideline.acknowledge');
        $this->actingAs($user);

        $site = Site::find($this->siteId);
        $site->update(['guidelines_version' => 2]);

        $response = $this->postForSite('/api/open-collab/onboarding/guidelines', [
            'version' => 2,
            'agreed' => true,
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('oc_user_guidelines_acknowledgements', [
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'version' => 2,
        ]);
    }

    public function test_store_bank_transfer_payment_details_persists_without_completing_step(): void
    {
        $user = $this->createAuthorizedOnboardingUser('onboarding.view');
        $this->actingAs($user);

        $response = $this->postForSite('/api/open-collab/onboarding/payment', [
            'payment_method_type' => 'bank_transfer',
            'stripe_token' => 'manual-reference-123',
            'tax_country' => 'GB',
        ], [], ['Accept' => 'application/json']);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('oc_contributor_profiles', [
            'user_id' => $user->id,
            'payment_method_type' => 'bank_transfer',
            'payment_details' => 'manual-reference-123',
            'tax_country' => 'GB',
        ]);
    }

    public function test_store_stripe_payment_details_uses_payment_method_service(): void
    {
        $user = $this->createAuthorizedOnboardingUser('onboarding.view');
        $this->actingAs($user);

        $this->bindPaymentMethodService(fn($mock) => $mock->shouldReceive('addForUser')
            ->once()
            ->with(Mockery::on(fn($actualUser) => (int)$actualUser->id === (int)$user->id), 'pm_card_123', 'GB', true)
            ->andReturn([
                'success' => true,
                'payment_methods' => [[
                    'id' => 'pm_card_123',
                    'brand' => 'visa',
                    'last4' => '4242',
                    'exp_month' => 12,
                    'exp_year' => 2030,
                    'is_default' => true,
                ]],
                'default_payment_method_id' => 'pm_card_123',
            ]));

        $response = $this->postForSite('/api/open-collab/onboarding/payment', [
            'payment_method_type' => 'stripe',
            'payment_method_id' => 'pm_card_123',
            'tax_country' => 'GB',
        ], [], ['Accept' => 'application/json']);
        $payload = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('pm_card_123', $payload['data']['default_payment_method_id']);
        $this->assertSame([], ContributorProfile::where('user_id', $user->id)->get()->toArray());
    }

    public function test_payment_methods_endpoint_returns_saved_cards_for_authorized_user(): void
    {
        $user = $this->createAuthorizedOnboardingUser('onboarding.view');
        $this->actingAs($user);

        $this->bindPaymentMethodService(fn($mock) => $mock->shouldReceive('listForUser')
            ->once()
            ->with(Mockery::on(fn($actualUser) => (int)$actualUser->id === (int)$user->id))
            ->andReturn([
                'success' => true,
                'payment_methods' => [[
                    'id' => 'pm_saved',
                    'brand' => 'mastercard',
                    'last4' => '4444',
                    'exp_month' => 8,
                    'exp_year' => 2031,
                    'is_default' => true,
                ]],
                'default_payment_method_id' => 'pm_saved',
            ]));

        $response = $this->getForSite('/api/open-collab/onboarding/payment-methods', ['Accept' => 'application/json']);
        $payload = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('pm_saved', $payload['data']['payment_methods'][0]['id']);
        $this->assertEquals('pm_saved', $payload['data']['default_payment_method_id']);
    }

    public function test_set_default_payment_method_endpoint_delegates_to_service(): void
    {
        $user = $this->createAuthorizedOnboardingUser('onboarding.view');
        $this->actingAs($user);

        $this->bindPaymentMethodService(fn($mock) => $mock->shouldReceive('setDefaultForUser')
            ->once()
            ->with(Mockery::on(fn($actualUser) => (int)$actualUser->id === (int)$user->id), 'pm_default')
            ->andReturn([
                'success' => true,
                'payment_methods' => [],
                'default_payment_method_id' => 'pm_default',
            ]));

        $response = $this->postForSite('/api/open-collab/onboarding/payment-methods/pm_default/default', [], [], ['Accept' => 'application/json']);
        $payload = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('pm_default', $payload['data']['default_payment_method_id']);
    }

    public function test_remove_payment_method_endpoint_delegates_to_service(): void
    {
        $user = $this->createAuthorizedOnboardingUser('onboarding.view');
        $this->actingAs($user);

        $this->bindPaymentMethodService(fn($mock) => $mock->shouldReceive('removeForUser')
            ->once()
            ->with(Mockery::on(fn($actualUser) => (int)$actualUser->id === (int)$user->id), 'pm_remove')
            ->andReturn([
                'success' => true,
                'payment_methods' => [],
                'default_payment_method_id' => null,
            ]));

        $response = $this->deleteForSite('/api/open-collab/onboarding/payment-methods/pm_remove', ['Accept' => 'application/json']);

        $this->assertEquals(200, $response->getStatusCode());
    }

    private function createAuthorizedOnboardingUser(string $permissionSlug): User
    {
        $user = $this->createUser([
            'email' => sprintf('onboarding-%s@example.com', str_replace('.', '-', $permissionSlug)),
            'role' => 'user',
            'is_contributor' => true,
        ]);

        $permission = OpenCollabPermission::where('slug', $permissionSlug)->first();
        OpenCollabSiteUserPermission::create([
            'site_id' => $this->siteId,
            'user_id' => $user->id,
            'permission_id' => $permission->id,
            'granted' => true,
        ]);

        return $user;
    }

    private function bindPaymentMethodService(callable $configure): void
    {
        Container::getInstance()->bind(ContributorPaymentMethodService::class, function () use ($configure) {
            $mock = Mockery::mock(ContributorPaymentMethodService::class);
            $configure($mock);
            return $mock;
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

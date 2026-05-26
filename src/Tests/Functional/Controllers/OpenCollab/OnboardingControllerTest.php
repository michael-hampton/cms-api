<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Framework\Support\Config;
use App\Models\Contract;
use App\Models\OpenCollabPermission;
use App\Models\OpenCollabSiteUserPermission;
use App\Models\Site;
use App\Models\User;
use App\Repositories\OpenCollab\RbacRepository;
use App\Services\OpenCollab\RbacBootstrapper;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

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
}

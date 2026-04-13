<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Enums\OpenCollab\PayoutStatus;
use App\Enums\Pages\PageStatus;
use App\Models\Invitation;
use App\Models\Payout;
use App\Models\Site;
use App\Models\User;
use App\Models\UserSite;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class AdminContributorControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private User $contributor;
    private User $otherContributor;

    public function test_admin_can_list_and_show_site_contributors(): void
    {
        UserSite::create(['user_id' => $this->contributor->id, 'site_id' => $this->siteId]);
        $otherSite = Site::create(['name' => 'Other Site', 'slug' => 'other-site-admin', 'is_default' => false]);
        UserSite::create(['user_id' => $this->otherContributor->id, 'site_id' => $otherSite->id]);

        $response = $this->getForSite('/api/open-collab/admin/contributors?q=Site Contributor');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $items = array_values(array_filter($data['data'], static fn($key) => is_int($key), ARRAY_FILTER_USE_KEY));

        $this->assertCount(1, $items);
        $this->assertEquals($this->contributor->id, $items[0]['id']);

        $showResponse = $this->getForSite("/api/open-collab/admin/contributors/{$this->contributor->id}");
        $showData = json_decode($showResponse->getContent(), true);

        $this->assertEquals(200, $showResponse->getStatusCode());
        $this->assertEquals($this->contributor->email, $showData['data']['contributor']['email']);
    }

    public function test_show_returns_404_for_contributor_without_site_access(): void
    {
        $response = $this->getForSite("/api/open-collab/admin/contributors/{$this->otherContributor->id}");

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_admin_can_deactivate_and_reactivate_contributor(): void
    {
        UserSite::create(['user_id' => $this->contributor->id, 'site_id' => $this->siteId]);

        $deactivateResponse = $this->postForSite("/api/open-collab/admin/contributors/{$this->contributor->id}/deactivate");
        $this->assertEquals(200, $deactivateResponse->getStatusCode());
        $this->assertDatabaseHas('users', ['id' => $this->contributor->id, 'is_active' => 0]);

        $reactivateResponse = $this->postForSite("/api/open-collab/admin/contributors/{$this->contributor->id}/reactivate");
        $this->assertEquals(200, $reactivateResponse->getStatusCode());
        $this->assertDatabaseHas('users', ['id' => $this->contributor->id, 'is_active' => 1]);
    }

    public function test_admin_can_close_contributor_account(): void
    {
        UserSite::create(['user_id' => $this->contributor->id, 'site_id' => $this->siteId]);

        $pendingPayout = Payout::create([
            'user_id' => $this->contributor->id,
            'site_id' => $this->siteId,
            'amount' => 7500,
            'currency' => 'GBP',
            'status' => PayoutStatus::Pending->value,
            'method' => 'bank_transfer',
        ]);

        $page = $this->createPage([
            'contributor_id' => $this->contributor->id,
            'status' => PageStatus::DRAFT->value,
        ]);

        $response = $this->postForSite("/api/open-collab/admin/contributors/{$this->contributor->id}/close", [
            'reason' => 'Repeated policy breaches.',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('users', ['id' => $this->contributor->id, 'is_active' => 0]);
        $this->assertDatabaseMissing('oc_user_sites', ['user_id' => $this->contributor->id, 'site_id' => $this->siteId]);
        $this->assertDatabaseHas('oc_payouts', [
            'id' => $pendingPayout->id,
            'status' => PayoutStatus::Rejected->value,
            'rejection_reason' => 'Account closed.',
        ]);
        $this->assertDatabaseHas('pages', ['id' => $page->id, 'status' => PageStatus::ARCHIVED->value]);
    }

    public function test_close_returns_422_when_reason_is_invalid(): void
    {
        UserSite::create(['user_id' => $this->contributor->id, 'site_id' => $this->siteId]);

        $response = $this->postForSite("/api/open-collab/admin/contributors/{$this->contributor->id}/close", [
            'reason' => 'bad',
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_admin_can_grant_and_revoke_site_access(): void
    {
        $grantResponse = $this->postForSite("/api/open-collab/admin/contributors/{$this->otherContributor->id}/grant-access");
        $this->assertEquals(200, $grantResponse->getStatusCode());
        $this->assertDatabaseHas('oc_user_sites', ['user_id' => $this->otherContributor->id, 'site_id' => $this->siteId]);

        $revokeResponse = $this->postForSite("/api/open-collab/admin/contributors/{$this->otherContributor->id}/revoke-access");
        $this->assertEquals(200, $revokeResponse->getStatusCode());
        $this->assertDatabaseMissing('oc_user_sites', ['user_id' => $this->otherContributor->id, 'site_id' => $this->siteId]);
    }

    public function test_admin_can_list_resend_and_revoke_invitations(): void
    {
        $invitation = Invitation::create([
            'site_id' => $this->siteId,
            'email' => 'invitee@example.com',
            'token' => bin2hex(random_bytes(32)),
            'invited_by' => $this->authenticatedUser->id,
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);

        $listResponse = $this->getForSite('/api/open-collab/admin/invitations');
        $listData = json_decode($listResponse->getContent(), true);
        $items = array_values(array_filter($listData, static fn($key) => is_int($key), ARRAY_FILTER_USE_KEY));

        $this->assertEquals(200, $listResponse->getStatusCode());
        $this->assertCount(1, $items);
        $this->assertEquals($invitation->email, $items[0]['email']);

        $resendResponse = $this->postForSite("/api/open-collab/admin/invitations/{$invitation->id}/resend");
        $resendData = json_decode($resendResponse->getContent(), true);

        $this->assertEquals(201, $resendResponse->getStatusCode());
        $this->assertEquals($invitation->email, $resendData['data']['invitation']['email']);

        $revokeResponse = $this->deleteForSite("/api/open-collab/admin/invitations/{$invitation->id}");
        $this->assertEquals(200, $revokeResponse->getStatusCode());

        $refreshed = Invitation::find($invitation->id);
        $this->assertNotNull($refreshed->revoked_at);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->contributor = $this->createUser([
            'name' => 'Site Contributor',
            'email' => 'site-contributor@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
            'is_active' => true,
        ]);

        $this->otherContributor = $this->createUser([
            'name' => 'Off Site Contributor',
            'email' => 'offsite@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
            'is_active' => true,
        ]);
    }
}

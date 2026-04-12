<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Models\Invitation;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ResendInvitationControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_resend_invitation_returns_success_response(): void
    {
        Invitation::create([
            'site_id' => $this->siteId,
            'email' => 'test@example.com',
            'token' => bin2hex(random_bytes(32)),
            'status' => 'pending',
            'invited_by' => $this->authenticatedUser->id,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+72 hours')),
        ]);

        $response = $this->postForSite('/api/open-collab/invitations/resend', [
            'email' => 'test@example.com',
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(
            'If an invitation exists for that address, a fresh link has been sent.',
            $data['message']
        );
    }

    public function test_resend_requires_valid_email(): void
    {
        $response = $this->postForSite('/api/open-collab/invitations/resend', [
            'email' => 'not-an-email',
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        // Should NOT throw or break
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('message', $data);
    }

    public function test_resend_does_nothing_when_no_invitation_exists(): void
    {
        $response = $this->postForSite('/api/open-collab/invitations/resend', [
            'email' => 'missing@example.com',
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $this->assertDatabaseMissing('oc_invitations', [
            'email' => 'missing@example.com',
            'site_id' => $this->siteId,
        ]);
    }

    public function test_resend_creates_new_invitation_when_expired(): void
    {
        $user = $this->createUser();
        Invitation::create([
            'site_id' => $this->siteId,
            'email' => 'expired@example.com',
            'token' => bin2hex(random_bytes(32)),
            'status' => 'expired',
            'invited_by' => $user->id,
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        $response = $this->postForSite('/api/open-collab/invitations/resend', [
            'email' => 'expired@example.com',
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $this->assertDatabaseHas('oc_invitations', [
            'email' => 'expired@example.com',
            'site_id' => $this->siteId,
        ]);
    }

    public function test_resend_skips_used_invitations(): void
    {
        Invitation::create([
            'site_id' => $this->siteId,
            'email' => 'used@example.com',
            'token' => bin2hex(random_bytes(32)),
            'status' => 'used',
            'invited_by' => $this->authenticatedUser->id,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+72 hours')),
        ]);

        $response = $this->postForSite('/api/open-collab/invitations/resend', [
            'email' => 'used@example.com',
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        // ensure no duplicate creation
        $this->assertDatabaseCount('oc_invitations', 1);
    }

    public function test_resend_uses_existing_pending_invitation(): void
    {
        Invitation::create([
            'site_id' => $this->siteId,
            'email' => 'pending@example.com',
            'token' => bin2hex(random_bytes(32)),
            'status' => 'pending',
            'invited_by' => $this->authenticatedUser->id,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+72 hours')),
        ]);

        $response = $this->postForSite('/api/open-collab/invitations/resend', [
            'email' => 'pending@example.com',
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $this->assertDatabaseHas('oc_invitations', [
            'email' => 'pending@example.com',
            'site_id' => $this->siteId,
        ]);
    }
}
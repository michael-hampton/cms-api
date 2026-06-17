<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Models\Invitation;
use App\Models\Site;
use App\Models\User;
use App\Models\UserSite;
use App\Framework\Authorization\Auth;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class InvitationControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // -------------------------------------------------------------------------
    // POST /api/{site}/open-collab/invitations (admin creates invite)
    // -------------------------------------------------------------------------

    public function test_admin_can_create_invitation(): void
    {
        $response = $this->postForSite('/api/open-collab/invitations', [
            'email' => 'newcontributor@example.com',
        ]);

        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('invitation', $data['data']);
        $this->assertEquals('newcontributor@example.com', $data['data']['invitation']['email']);
        $this->assertArrayHasKey('expires_at', $data['data']['invitation']);

        $this->assertDatabaseHas('oc_invitations', [
            'email' => 'newcontributor@example.com',
            'site_id' => $this->siteId,
        ]);
    }

    public function test_create_invitation_requires_valid_email(): void
    {
        $response = $this->postForSite('/api/open-collab/invitations', [
            'email' => 'not-an-email',
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_create_invitation_returns_422_when_pending_invite_already_exists(): void
    {
        Invitation::create([
            'site_id' => $this->siteId,
            'email' => 'duplicate@example.com',
            'token' => bin2hex(random_bytes(32)),
            'invited_by' => $this->authenticatedUser->id,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+72 hours')),
        ]);

        $response = $this->postForSite('/api/open-collab/invitations', [
            'email' => 'duplicate@example.com',
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_create_invitation_succeeds_when_previous_invitation_is_expired(): void
    {
        // An expired invitation should NOT block creating a fresh one
        Invitation::create([
            'site_id' => $this->siteId,
            'email' => 'expired-then-reinvite@example.com',
            'token' => bin2hex(random_bytes(32)),
            'invited_by' => $this->authenticatedUser->id,
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 hour')), // expired
        ]);

        $response = $this->postForSite('/api/open-collab/invitations', [
            'email' => 'expired-then-reinvite@example.com',
        ]);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function test_create_invitation_succeeds_when_user_already_has_account(): void
    {
        // A user account existing should NOT block a new invitation (re-invitation after closure)
        User::create([
            'name' => 'Existing Contributor',
            'email' => 'existing-contributor@example.com',
            'password' => password_hash('secret', PASSWORD_DEFAULT),
            'role' => 'contributor',
            'is_contributor' => true,
            'is_active' => false, // closed account
        ]);

        $response = $this->postForSite('/api/open-collab/invitations', [
            'email' => 'existing-contributor@example.com',
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertDatabaseHas('oc_invitations', [
            'email' => 'existing-contributor@example.com',
            'site_id' => $this->siteId,
        ]);
    }

    public function test_create_invitation_succeeds_when_previous_invitation_was_revoked(): void
    {
        Invitation::create([
            'site_id' => $this->siteId,
            'email' => 'revoked-user@example.com',
            'token' => bin2hex(random_bytes(32)),
            'invited_by' => $this->authenticatedUser->id,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+72 hours')),
            'revoked_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);

        $response = $this->postForSite('/api/open-collab/invitations', [
            'email' => 'revoked-user@example.com',
        ]);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function test_unauthenticated_user_cannot_create_invitation(): void
    {
        $response = $this->postForSiteUnauthenticated('/api/open-collab/invitations', [
            'email' => 'someone@example.com',
        ]);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_user_without_invitation_permissions_cannot_create_invitation(): void
    {
        $this->enableSiteRbac();

        $restrictedUser = $this->createUser([
            'email' => 'invitation-restricted@example.com',
            'role' => 'user',
            'is_contributor' => true,
        ]);
        $this->actingAs($restrictedUser);

        $response = $this->postForSite('/api/open-collab/invitations', [
            'email' => 'blocked@example.com',
        ]);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertDatabaseMissing('oc_invitations', [
            'email' => 'blocked@example.com',
            'site_id' => $this->siteId,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/{site}/open-collab/invitations/{token}/accept (guest accepts)
    // -------------------------------------------------------------------------

    public function test_guest_can_accept_valid_invitation(): void
    {
        $token = bin2hex(random_bytes(32));
        $invitation = Invitation::create([
            'site_id' => $this->siteId,
            'email' => 'invited@example.com',
            'token' => $token,
            'invited_by' => $this->authenticatedUser->id,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+72 hours')),
        ]);

        $response = $this->postForSiteUnauthenticated(
            "/api/open-collab/invitations/{$token}/accept",
            ['name' => 'New Contributor', 'password' => 'password123']
        );

        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('token', $data['data']);
        $this->assertEquals('contributor', $data['data']['user']['role']);

        $this->assertDatabaseHas('users', [
            'email' => 'invited@example.com',
            'is_contributor' => 1,
        ]);

        // Invitation must be marked used
        $this->assertDatabaseHas('oc_invitations', [
            'id' => $invitation->id,
        ]);
        $refreshed = Invitation::find($invitation->id);
        $this->assertNotNull($refreshed->used_at);
    }

    public function test_accept_issues_hashed_expiring_open_collab_token_through_auth_layer(): void
    {
        $token = bin2hex(random_bytes(32));
        Invitation::create([
            'site_id' => $this->siteId,
            'email' => 'scoped-token@example.com',
            'token' => $token,
            'invited_by' => $this->authenticatedUser->id,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+72 hours')),
        ]);

        $response = $this->postForSiteUnauthenticated(
            "/api/open-collab/invitations/{$token}/accept",
            ['name' => 'Scoped Contributor', 'password' => 'password123']
        );

        $this->assertEquals(201, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $plainTextToken = $payload['data']['token'];
        $userId = $payload['data']['user']['id'];

        $rows = $this->database->query(
            'SELECT * FROM personal_access_tokens WHERE tokenable_id = ?',
            [$userId]
        )->fetchAll();

        $this->assertCount(1, $rows);
        $this->assertSame(hash('sha256', $plainTextToken), $rows[0]['token']);
        $this->assertNotSame($plainTextToken, $rows[0]['token']);
        $this->assertSame(json_encode(['open-collab']), $rows[0]['abilities']);
        $this->assertNotEmpty($rows[0]['expires_at']);
    }

    public function test_accept_switches_existing_web_session_to_invited_user(): void
    {
        $previousUserId = $this->authenticatedUser->id;
        $token = bin2hex(random_bytes(32));
        Invitation::create([
            'site_id' => $this->siteId,
            'email' => 'session-switch@example.com',
            'token' => $token,
            'invited_by' => $previousUserId,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+72 hours')),
        ]);

        $response = $this->postForSite(
            "/api/open-collab/invitations/{$token}/accept",
            ['name' => 'Session Switch', 'password' => 'password123']
        );

        $this->assertEquals(201, $response->getStatusCode());

        $payload = json_decode($response->getContent(), true);
        $this->assertNotSame($previousUserId, $payload['data']['user']['id']);
        $this->assertSame((int) $payload['data']['user']['id'], Auth::id());
    }

    public function test_invitation_token_cannot_be_used_as_api_bearer_token(): void
    {
        $invitationToken = bin2hex(random_bytes(32));
        Invitation::create([
            'site_id' => $this->siteId,
            'email' => 'not-a-bearer@example.com',
            'token' => $invitationToken,
            'invited_by' => $this->authenticatedUser->id,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+72 hours')),
        ]);

        $response = $this->getForSiteUnauthenticated('/api/auth/me', [
            'Authorization' => 'Bearer ' . $invitationToken,
            'Accept' => 'application/json',
        ]);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_invited_contributor_can_login_through_standard_open_collab_auth_flow(): void
    {
        $token = bin2hex(random_bytes(32));
        Invitation::create([
            'site_id' => $this->siteId,
            'email' => 'login-after-invite@example.com',
            'token' => $token,
            'invited_by' => $this->authenticatedUser->id,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+72 hours')),
        ]);

        $this->postForSiteUnauthenticated(
            "/api/open-collab/invitations/{$token}/accept",
            ['name' => 'Login Contributor', 'password' => 'password123']
        );

        $response = $this->postForSiteUnauthenticated('/api/open-collab/auth/login', [
            'email' => 'login-after-invite@example.com',
            'password' => 'password123',
        ], [], [
            'Accept' => 'application/json',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertNotEmpty($payload['data']['token']);
        $this->assertSame('login-after-invite@example.com', $payload['data']['user']['email']);
    }

    public function test_bearer_token_can_authenticate_other_site_only_when_user_has_site_access(): void
    {
        $token = bin2hex(random_bytes(32));
        Invitation::create([
            'site_id' => $this->siteId,
            'email' => 'cross-site@example.com',
            'token' => $token,
            'invited_by' => $this->authenticatedUser->id,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+72 hours')),
        ]);

        $acceptResponse = $this->postForSiteUnauthenticated(
            "/api/open-collab/invitations/{$token}/accept",
            ['name' => 'Cross Site Contributor', 'password' => 'password123']
        );

        $payload = json_decode($acceptResponse->getContent(), true);
        $bearerToken = $payload['data']['token'];
        $userId = $payload['data']['user']['id'];
        $otherSite = Site::create([
            'name' => 'Other Site',
            'slug' => 'other-site',
            'is_default' => false,
            'is_active' => true,
        ]);

        $denied = $this->makeRequest('GET', "/api/{$otherSite->slug}/auth/me", [], [
            'Authorization' => 'Bearer ' . $bearerToken,
            'X-Site-Id' => $otherSite->id,
            'Accept' => 'application/json',
        ]);
        $this->assertEquals(403, $denied->getStatusCode());

        UserSite::create([
            'user_id' => $userId,
            'site_id' => $otherSite->id,
        ]);

        $allowed = $this->makeRequest('GET', "/api/{$otherSite->slug}/auth/me", [], [
            'Authorization' => 'Bearer ' . $bearerToken,
            'X-Site-Id' => $otherSite->id,
            'Accept' => 'application/json',
        ]);

        $this->assertEquals(200, $allowed->getStatusCode());
    }

    public function test_accept_reactivates_existing_user_account_without_overwriting_credentials(): void
    {
        $existingUser = User::create([
            'name' => 'Old Name',
            'email' => 'returning@example.com',
            'password' => password_hash('oldpass', PASSWORD_DEFAULT),
            'role' => 'contributor',
            'is_contributor' => true,
            'is_active' => false,
            'site_id' => $this->siteId,
        ]);

        $token = bin2hex(random_bytes(32));

        Invitation::create([
            'site_id' => $this->siteId,
            'email' => 'returning@example.com',
            'token' => $token,
            'invited_by' => $this->authenticatedUser->id,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+72 hours')),
        ]);

        $response = $this->postForSiteUnauthenticated(
            "/api/open-collab/invitations/{$token}/accept",
            ['name' => 'New Name', 'password' => 'newpassword123']
        );

        $this->assertEquals(201, $response->getStatusCode());

        $this->assertDatabaseHas('users', [
            'id' => $existingUser->id,
            'is_active' => 1,
            'name' => 'Old Name',
        ]);

        $this->assertEquals(
            1,
            User::where('email', 'returning@example.com')->count()
        );

        $existingUser = User::find($existingUser->id);

        $this->assertTrue(password_verify('oldpass', $existingUser->password));
        $this->assertFalse(password_verify('newpassword123', $existingUser->password));
    }

    public function test_accept_returns_404_for_invalid_token(): void
    {
        $response = $this->postForSiteUnauthenticated(
            '/api/open-collab/invitations/ghost-token/accept',
            ['name' => 'Ghost', 'password' => 'password123']
        );

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_accept_returns_404_for_expired_invitation(): void
    {
        $token = bin2hex(random_bytes(32));
        Invitation::create([
            'site_id' => $this->siteId,
            'email' => 'expired@example.com',
            'token' => $token,
            'invited_by' => $this->authenticatedUser->id,
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);

        $response = $this->postForSiteUnauthenticated(
            "/api/open-collab/invitations/{$token}/accept",
            ['name' => 'Late', 'password' => 'password123']
        );

        $this->assertEquals(404, $response->getStatusCode());

        $this->assertDatabaseMissing('users', ['email' => 'expired@example.com']);
    }

    public function test_accept_returns_404_for_already_used_invitation(): void
    {
        $token = bin2hex(random_bytes(32));
        Invitation::create([
            'site_id' => $this->siteId,
            'email' => 'used@example.com',
            'token' => $token,
            'invited_by' => $this->authenticatedUser->id,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+72 hours')),
            'used_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);

        $response = $this->postForSiteUnauthenticated(
            "/api/open-collab/invitations/{$token}/accept",
            ['name' => 'Reuse Attempt', 'password' => 'password123']
        );

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_accept_requires_name_and_password(): void
    {
        $token = bin2hex(random_bytes(32));
        Invitation::create([
            'site_id' => $this->siteId,
            'email' => 'validation@example.com',
            'token' => $token,
            'invited_by' => $this->authenticatedUser->id,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+72 hours')),
        ]);

        $response = $this->postForSiteUnauthenticated(
            "/api/open-collab/invitations/{$token}/accept",
            []
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_accept_requires_password_minimum_length(): void
    {
        $token = bin2hex(random_bytes(32));
        Invitation::create([
            'site_id' => $this->siteId,
            'email' => 'shortpass@example.com',
            'token' => $token,
            'invited_by' => $this->authenticatedUser->id,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+72 hours')),
        ]);

        $response = $this->postForSiteUnauthenticated(
            "/api/open-collab/invitations/{$token}/accept",
            ['name' => 'Short Pass', 'password' => 'abc']
        );

        $this->assertEquals(422, $response->getStatusCode());
    }
}

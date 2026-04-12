<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Models\Invitation;
use App\Models\Model;
use App\Models\User;
use App\Repositories\OpenCollab\InvitationRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class InvitationRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private InvitationRepository $repository;
    private User $user;

    // ── hasPendingInviteForEmail() ─────────────────────────────────────────────

    public function test_has_pending_invite_returns_true_for_valid_invite(): void
    {
        $this->makeInvitation();

        $this->assertTrue($this->repository->hasPendingInviteForEmail('test@example.com', 1));
    }

    public function test_has_pending_invite_returns_false_when_used(): void
    {
        $this->makeInvitation(['used_at' => now()]);

        $this->assertFalse($this->repository->hasPendingInviteForEmail('test@example.com', 1));
    }

    public function test_has_pending_invite_returns_false_when_revoked(): void
    {
        $this->makeInvitation(['revoked_at' => now()]);

        $this->assertFalse($this->repository->hasPendingInviteForEmail('test@example.com', 1));
    }

    public function test_has_pending_invite_returns_false_when_expired(): void
    {
        $this->makeInvitation(['expires_at' => date('Y-m-d H:i:s', strtotime('-1 day'))]);

        $this->assertFalse($this->repository->hasPendingInviteForEmail('test@example.com', 1));
    }

    public function test_has_pending_invite_is_scoped_to_site(): void
    {
        $this->makeInvitation(['site_id' => $this->siteId]);

        $this->assertFalse($this->repository->hasPendingInviteForEmail('test@example.com', 2));
    }

    // ── findByToken() ─────────────────────────────────────────────────────────

    public function test_find_by_token_returns_correct_invitation(): void
    {
        $inv = $this->makeInvitation(['token' => 'uniquetoken123']);

        $found = $this->repository->findByToken('uniquetoken123');

        $this->assertNotNull($found);
        $this->assertEquals($inv->id, $found->id);
    }

    public function test_find_by_token_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->findByToken('doesnotexist'));
    }

    // ── markAsUsed() ──────────────────────────────────────────────────────────

    public function test_mark_as_used_sets_used_at(): void
    {
        $inv = $this->makeInvitation();

        $this->repository->markAsUsed($inv->id, $this->user->id);

        $updated = Invitation::find($inv->id);
        $this->assertNotNull($updated->used_at);
    }

    // ── revoke() ──────────────────────────────────────────────────────────────

    public function test_revoke_sets_revoked_at_and_revoked_by(): void
    {
        $inv = $this->makeInvitation();

        $this->repository->revoke($inv->id, revokedBy: $this->user->id);

        $updated = Invitation::find($inv->id);
        $this->assertNotNull($updated->revoked_at);
        $this->assertEquals($this->user->id, $updated->revoked_by);
    }

    // ── expireAllForEmail() ───────────────────────────────────────────────────

    public function test_expire_all_for_email_marks_all_non_used_invitations_as_expired(): void
    {
        $pending = $this->makeInvitation(['email' => 'expire@example.com']);
        $revoked = $this->makeInvitation([
            'email' => 'expire@example.com',
            'revoked_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);
        // Already used — should NOT be touched
        $used = $this->makeInvitation([
            'email' => 'expire@example.com',
            'used_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);

        $this->repository->expireAllForEmail('expire@example.com', $this->siteId);

        // Pending invitation should now be expired (expires_at set to now or past)
        $updatedPending = Invitation::find($pending->id);
        $this->assertLessThanOrEqual(
            now_datetime(),
            $updatedPending->expires_at,
        );

        // Used invitation must remain untouched
        $updatedUsed = Invitation::find($used->id);
        $this->assertNotNull($updatedUsed->used_at);
    }

    public function test_expire_all_for_email_does_not_affect_other_emails(): void
    {
        $ownInvite = $this->makeInvitation(['email' => 'target@example.com']);
        $otherInvite = $this->makeInvitation(['email' => 'other@example.com']);
        $futureExpiry = date('Y-m-d H:i:s', strtotime('+72 hours'));

        $this->repository->expireAllForEmail('target@example.com', $this->siteId);

        // Other email's invite must be unchanged
        $updated = Invitation::find($otherInvite->id);
        $this->assertGreaterThan(now_datetime(), $updated->expires_at);
    }

    public function test_expire_all_for_email_is_scoped_to_site(): void
    {
        $otherSite = $this->createSite();
        $otherSiteInvite = $this->makeInvitation([
            'email' => 'test@example.com',
            'site_id' => $otherSite->id,
        ]);
        $futureExpiry = date('Y-m-d H:i:s', strtotime('+72 hours'));

        $this->repository->expireAllForEmail('test@example.com', $this->siteId);

        // Other-site invitation must be unchanged
        $updated = Invitation::find($otherSiteInvite->id);
        $this->assertGreaterThan(now_datetime(), $updated->expires_at);
    }

    // ── recentResendCount() ───────────────────────────────────────────────────

    public function test_recent_resend_count_returns_correct_count_within_window(): void
    {
        // 2 invitations created within the last hour
        $this->makeInvitation(['email' => 'resend@example.com', 'created_at' => date('Y-m-d H:i:s', strtotime('-30 minutes'))]);
        $this->makeInvitation(['email' => 'resend@example.com', 'created_at' => date('Y-m-d H:i:s', strtotime('-45 minutes'))]);
        // 1 outside the window
        $this->makeInvitation(['email' => 'resend@example.com', 'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))]);

        $count = $this->repository->recentResendCount('resend@example.com', $this->siteId);

        $this->assertEquals(3, $count);
    }

    public function test_recent_resend_count_returns_zero_when_no_recent_invitations(): void
    {
        $this->makeInvitation(['email' => 'noresend@example.com', 'created_at' => date('Y-m-d H:i:s', strtotime('-3 hours'))]);

        $count = $this->repository->recentResendCount('noresend@example.com', $this->siteId);

        $this->assertEquals(1, $count);
    }

    public function test_recent_resend_count_is_scoped_to_site(): void
    {
        $otherSite = $this->createSite();
        $this->makeInvitation(['email' => 'test@example.com', 'site_id' => $otherSite->id]);

        $count = $this->repository->recentResendCount('test@example.com', $this->siteId);

        $this->assertEquals(0, $count);
    }

    // ── getAllForSite() ───────────────────────────────────────────────────────

    public function test_get_all_for_site_returns_only_that_sites_invitations(): void
    {
        $otherSite = $this->createSite();
        $this->makeInvitation(['site_id' => $this->siteId, 'email' => 'a@example.com']);
        $this->makeInvitation(['site_id' => $this->siteId, 'email' => 'b@example.com']);
        $this->makeInvitation(['site_id' => $otherSite->id, 'email' => 'c@example.com']);

        $results = $this->repository->getAllForSite($this->siteId);

        $this->assertCount(2, $results);
        $results->each(fn($inv) => $this->assertEquals($this->siteId, $inv->site_id));
    }

    public function test_get_all_for_site_orders_newest_first(): void
    {
        $this->makeInvitation(['site_id' => $this->siteId, 'email' => 'old@example.com', 'created_at' => '2024-01-01']);
        $this->makeInvitation(['site_id' => $this->siteId, 'email' => 'new@example.com', 'created_at' => '2024-12-01']);

        $results = $this->repository->getAllForSite($this->siteId);

        $this->assertEquals('new@example.com', $results->first()->email);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeInvitation(array $overrides = []): Model
    {
        return Invitation::create(array_merge([
            'email' => 'test@example.com',
            'site_id' => $this->siteId,
            'token' => bin2hex(random_bytes(16)),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'used_at' => null,
            'revoked_at' => null,
            'invited_by' => $this->user->id,
        ], $overrides));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new InvitationRepository();
        $this->user = $this->createUser();
    }
}
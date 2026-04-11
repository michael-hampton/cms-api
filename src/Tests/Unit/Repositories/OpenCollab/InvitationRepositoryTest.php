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

    public function test_has_pending_invite_returns_true_for_valid_invite(): void
    {
        $this->makeInvitation();

        $this->assertTrue($this->repository->hasPendingInviteForEmail('test@example.com', 1));
    }

    private function makeInvitation(array $overrides = []): Model
    {
        return Invitation::create(array_merge([
            'email' => 'test@example.com',
            'site_id' => 1,
            'token' => bin2hex(random_bytes(16)),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'used_at' => null,
            'revoked_at' => null,
            'invited_by' => $this->user->id
        ], $overrides));
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

    public function test_mark_as_used_sets_used_at(): void
    {
        $inv = $this->makeInvitation();

        $this->repository->markAsUsed($inv->id, $this->user->id);

        $this->assertDatabaseHas('oc_invitations', [
            'id' => $inv->id,
        ]);
        $updated = Invitation::find($inv->id);
        $this->assertNotNull($updated->used_at);
    }

    public function test_revoke_sets_revoked_at_and_revoked_by(): void
    {
        $inv = $this->makeInvitation();

        $this->repository->revoke($inv->id, revokedBy: $this->user->id);

        $updated = Invitation::find($inv->id);
        $this->assertNotNull($updated->revoked_at);
        $this->assertEquals($this->user->id, $updated->revoked_by);
    }

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

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new InvitationRepository();
        $this->user = $this->createUser();
    }
}
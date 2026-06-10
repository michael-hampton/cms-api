<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\InvitationStatus;
use App\Models\Invitation;
use App\Services\OpenCollab\InvitationStateMachine;
use Mockery;
use PHPUnit\Framework\TestCase;

class InvitationStateMachineTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_can_resend_is_true_for_pending_invitations(): void
    {
        $machine = $this->makeWithStatus(InvitationStatus::Pending);

        $this->assertTrue($machine->canResend());
    }

    public function test_can_resend_is_true_for_expired_invitations(): void
    {
        $machine = $this->makeWithStatus(InvitationStatus::Expired);

        $this->assertTrue($machine->canResend());
    }

    public function test_can_resend_is_false_for_revoked_invitations(): void
    {
        $machine = $this->makeWithStatus(InvitationStatus::Revoked);

        $this->assertFalse($machine->canResend());
    }

    public function test_can_resend_is_false_for_used_invitations(): void
    {
        $machine = $this->makeWithStatus(InvitationStatus::Used);

        $this->assertFalse($machine->canResend());
    }

    public function test_should_create_new_invite_is_true_only_for_expired(): void
    {
        $this->assertTrue($this->makeWithStatus(InvitationStatus::Expired)->shouldCreateNewInvite());
        $this->assertFalse($this->makeWithStatus(InvitationStatus::Pending)->shouldCreateNewInvite());
        $this->assertFalse($this->makeWithStatus(InvitationStatus::Used)->shouldCreateNewInvite());
        $this->assertFalse($this->makeWithStatus(InvitationStatus::Revoked)->shouldCreateNewInvite());
    }

    public function test_assert_acceptable_throws_for_non_pending_status(): void
    {
        foreach ([InvitationStatus::Used, InvitationStatus::Expired, InvitationStatus::Revoked] as $status) {
            $machine = $this->makeWithStatus($status);

            try {
                $machine->assertAcceptable();
                $this->fail("Expected DomainException for status {$status->value}");
            } catch (\DomainException $e) {
                $this->assertStringContainsString($status->value, $e->getMessage());
            }
        }
    }

    public function test_assert_acceptable_does_not_throw_for_pending(): void
    {
        $machine = $this->makeWithStatus(InvitationStatus::Pending);

        $machine->assertAcceptable(); // No exception
        $this->addToAssertionCount(1);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeWithStatus(InvitationStatus $status): InvitationStateMachine
    {
        $invitation = Mockery::mock(Invitation::class);
        $invitation->shouldReceive('resolveStatus')->andReturn($status);

        return new InvitationStateMachine($invitation);
    }
}
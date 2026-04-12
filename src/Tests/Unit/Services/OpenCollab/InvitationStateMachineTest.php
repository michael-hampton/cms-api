<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\InvitationStatus;
use App\Models\Invitation;
use App\Services\OpenCollab\InvitationStateMachine;
use Mockery;
use PHPUnit\Framework\TestCase;

class InvitationStateMachineTest extends TestCase
{
    public function test_pending_invitation_can_be_accepted(): void
    {
        $invitation = Mockery::mock(Invitation::class);

        $invitation->shouldReceive('resolveStatus')
            ->once()
            ->andReturn(InvitationStatus::Pending);

        $state = new InvitationStateMachine($invitation);

        $this->assertTrue($state->isPending());
    }

    public function test_used_invitation_cannot_be_accepted(): void
    {
        $invitation = Mockery::mock(Invitation::class);

        $invitation->shouldReceive('resolveStatus')
            ->once()
            ->andReturn(InvitationStatus::Used);

        $state = new InvitationStateMachine($invitation);

        $this->assertTrue($state->isUsed());

        $this->expectException(\DomainException::class);

        $state->assertAcceptable();
    }
}
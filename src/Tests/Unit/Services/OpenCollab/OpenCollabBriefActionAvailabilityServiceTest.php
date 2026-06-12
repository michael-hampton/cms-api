<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Models\Brief;
use App\Models\Collaborator;
use App\Services\OpenCollab\OpenCollabBriefActionAvailabilityService;
use App\Services\OpenCollab\OpenCollabBriefStatusMapper;
use Mockery;
use PHPUnit\Framework\TestCase;

class OpenCollabBriefActionAvailabilityServiceTest extends TestCase
{
    private OpenCollabBriefActionAvailabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new OpenCollabBriefActionAvailabilityService(new OpenCollabBriefStatusMapper());
    }

    public function test_pending_assignment_can_be_accepted_rejected_or_negotiated(): void
    {
        $actions = $this->service->availableActions(
            $this->brief('draft'),
            $this->assignment('pending')
        );

        $this->assertSame(['accept', 'reject', 'negotiate', 'request_clarification'], $actions);
    }

    public function test_accepted_in_progress_assignment_can_submit(): void
    {
        $actions = $this->service->availableActions(
            $this->brief('in_progress'),
            $this->assignment('writer')
        );

        $this->assertContains('submit', $actions);
        $this->assertContains('request_deadline_change', $actions);
    }

    public function test_returned_assignment_can_resubmit(): void
    {
        $actions = $this->service->availableActions(
            $this->brief('on_hold'),
            $this->assignment('writer')
        );

        $this->assertContains('resubmit', $actions);
        $this->assertNotContains('submit', $actions);
    }

    public function test_unavailable_action_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->assertActionAvailable(
            'submit',
            $this->brief('ready'),
            $this->assignment('writer')
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function brief(string $status): Brief
    {
        $brief = Mockery::mock(Brief::class)->makePartial();
        $brief->status = $status;

        return $brief;
    }

    private function assignment(string $role): Collaborator
    {
        $assignment = Mockery::mock(Collaborator::class)->makePartial();
        $assignment->role = $role;

        return $assignment;
    }
}

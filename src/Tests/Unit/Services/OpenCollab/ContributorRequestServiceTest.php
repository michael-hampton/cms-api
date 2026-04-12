<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\ContributorRequest;
use App\Models\Invitation;
use App\Repositories\OpenCollab\ContributorRequestRepository;
use App\Repositories\OpenCollab\InvitationRepository;
use App\Services\OpenCollab\ContributorRequestService;
use App\Services\OpenCollab\InvitationService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class ContributorRequestServiceTest extends FunctionalTestCase
{
    private ContributorRequestService $service;
    private MockInterface $requestRepository;
    private MockInterface $invitationRepository;
    private MockInterface $invitationService;
    private MockInterface $databaseMock;
    private MockInterface $logger;

    // ─────────────────────────────────────────────────────────────────────────
    // submit()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_submit_queues_request_when_approval_required(): void
    {
        $request = $this->makeContributorRequest(['status' => 'pending']);

        $this->requestRepository->shouldReceive('hasPendingRequest')
            ->with('user@example.com', 1)->once()->andReturn(false);
        $this->invitationRepository->shouldReceive('hasPendingInviteForEmail')
            ->with('user@example.com', 1)->once()->andReturn(false);
        $this->requestRepository->shouldReceive('create')
            ->once()
            ->withArgs(fn($data) => $data['email'] === 'user@example.com' && $data['status'] === 'pending')
            ->andReturn($request);
        $this->logger->shouldReceive('info')->once();
        $this->invitationService->shouldNotReceive('create');

        $result = $this->service->submit('user@example.com', 'Jane', 'A long enough bio here.', 1, requiresApproval: true);

        $this->assertTrue($result['requires_approval']);
        $this->assertSame($request, $result['request']);
        $this->assertNull($result['invitation']);
    }

    private function makeContributorRequest(array $attributes = []): ContributorRequest
    {
        $defaults = ['id' => 1, 'site_id' => 1, 'email' => 'user@example.com', 'name' => 'Test User', 'bio' => 'Test bio.', 'status' => 'pending'];
        $r = new ContributorRequest(array_merge($defaults, $attributes));
        $r->exists = true;
        return $r;
    }

    public function test_submit_dispatches_invitation_immediately_when_no_approval_required(): void
    {
        $invitation = $this->makeInvitation();

        $this->requestRepository->shouldReceive('hasPendingRequest')->andReturn(false);
        $this->invitationRepository->shouldReceive('hasPendingInviteForEmail')->andReturn(false);
        $this->invitationService->shouldReceive('create')
            ->with('user@example.com', 0, 1)
            ->once()
            ->andReturn($invitation);
        $this->requestRepository->shouldNotReceive('create');
        $this->logger->shouldReceive('info')->once();

        $result = $this->service->submit('user@example.com', 'Jane', 'Bio', 1, requiresApproval: false);

        $this->assertFalse($result['requires_approval']);
        $this->assertNull($result['request']);
        $this->assertSame($invitation, $result['invitation']);
    }

    private function makeInvitation(): Invitation
    {
        $inv = new Invitation(['id' => 10, 'site_id' => 1, 'email' => 'user@example.com', 'token' => 'tok', 'expires_at' => date('Y-m-d H:i:s', strtotime('+72 hours'))]);
        $inv->exists = true;
        return $inv;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // approve()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_submit_throws_when_pending_request_already_exists(): void
    {
        $this->requestRepository->shouldReceive('hasPendingRequest')
            ->andReturn(true);
        $this->invitationService->shouldNotReceive('create');
        $this->requestRepository->shouldNotReceive('create');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/already pending/i');

        $this->service->submit('dup@example.com', 'Dup', 'Bio', 1, requiresApproval: true);
    }

    public function test_submit_throws_when_pending_invitation_already_exists(): void
    {
        $this->requestRepository->shouldReceive('hasPendingRequest')->andReturn(false);
        $this->invitationRepository->shouldReceive('hasPendingInviteForEmail')
            ->andReturn(true);
        $this->invitationService->shouldNotReceive('create');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/invitation for this email already exists/i');

        $this->service->submit('inv@example.com', 'Inv', 'Bio', 1, requiresApproval: true);
    }

    public function test_approve_dispatches_invitation_and_marks_request_approved(): void
    {
        $request = $this->makeContributorRequest(['id' => 5, 'status' => 'pending', 'site_id' => 1, 'email' => 'user@example.com']);
        $invitation = $this->makeInvitation();

        $this->requestRepository->shouldReceive('find')->with(5)->andReturn($request);
        $this->invitationService->shouldReceive('create')
            ->with('user@example.com', 99, 1)
            ->once()
            ->andReturn($invitation);
        $this->requestRepository->shouldReceive('update')
            ->once()
            ->withArgs(fn($id, $data) => $id === 5 && $data['status'] === 'approved' && $data['reviewed_by'] === 99);

        $result = $this->service->approve(5, adminId: 99);

        $this->assertSame($invitation, $result);
    }

    public function test_approve_throws_when_request_not_found(): void
    {
        $this->requestRepository->shouldReceive('find')->andReturn(null);
        $this->invitationService->shouldNotReceive('create');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not found/i');

        $this->service->approve(999, 99);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // reject()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_approve_throws_when_request_not_pending(): void
    {
        $request = $this->makeContributorRequest(['id' => 5, 'status' => 'approved']);
        $this->requestRepository->shouldReceive('find')->andReturn($request);
        $this->invitationService->shouldNotReceive('create');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not pending/i');

        $this->service->approve(5, 99);
    }

    public function test_approve_wraps_in_transaction(): void
    {
        $request = $this->makeContributorRequest(['id' => 5, 'status' => 'pending', 'site_id' => 1, 'email' => 'user@example.com']);
        $invitation = $this->makeInvitation();

        $this->requestRepository->shouldReceive('find')->andReturn($request);
        $this->invitationService->shouldReceive('create')->andReturn($invitation);
        $this->requestRepository->shouldReceive('update');

        // Transaction mock already wraps and executes the callback
        $result = $this->service->approve(5, 99);
        $this->assertSame($invitation, $result);
    }

    public function test_reject_marks_request_rejected_with_reason(): void
    {
        $request = $this->makeContributorRequest(['id' => 7, 'status' => 'pending']);
        $rejected = $this->makeContributorRequest(['id' => 7, 'status' => 'rejected']);

        $this->requestRepository->shouldReceive('find')
            ->with(7)
            ->andReturn($request, $rejected);
        $this->requestRepository->shouldReceive('update')
            ->once()
            ->withArgs(fn($id, $data) => $id === 7 &&
                $data['status'] === 'rejected' &&
                $data['reviewed_by'] === 99 &&
                $data['rejection_reason'] === 'Not a good fit.'
            );

        $result = $this->service->reject(7, 99, 'Not a good fit.');

        $this->assertEquals('rejected', $result->status);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // pendingForSite()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_reject_throws_when_request_not_found(): void
    {
        $this->requestRepository->shouldReceive('find')->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->reject(999, 99);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    public function test_reject_throws_when_request_not_pending(): void
    {
        $request = $this->makeContributorRequest(['status' => 'rejected']);
        $this->requestRepository->shouldReceive('find')->andReturn($request);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not pending/i');

        $this->service->reject(1, 99);
    }

    public function test_pending_for_site_delegates_to_repository(): void
    {
        $collection = new \App\Framework\Support\Collection([]);
        $this->requestRepository->shouldReceive('pendingForSite')->with(1)->once()->andReturn($collection);

        $result = $this->service->pendingForSite(1);

        $this->assertSame($collection, $result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->requestRepository = Mockery::mock(ContributorRequestRepository::class);
        $this->invitationRepository = Mockery::mock(InvitationRepository::class);
        $this->invitationService = Mockery::mock(InvitationService::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->logger = Mockery::mock(Logger::class);

        $this->databaseMock->shouldReceive('transaction')
            ->andReturnUsing(fn(callable $cb) => $cb());

        $this->service = new ContributorRequestService(
            $this->requestRepository,
            $this->invitationRepository,
            $this->invitationService,
            $this->databaseMock,
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
<?php

namespace App\Tests\Unit\Services\Cms\Briefs;

use App\Actions\Brief\LogBriefActivity;
use App\Enums\OpenCollab\BriefAssignmentRequestStatus;
use App\Enums\OpenCollab\BriefAssignmentRequestType;
use App\Exceptions\OpenCollab\BriefAssignmentRequestAlreadyResolvedException;
use App\Exceptions\OpenCollab\DuplicateActiveRequestException;
use App\Framework\Database\Database;
use App\Models\Brief;
use App\Models\BriefActivityLog;
use App\Models\BriefAssignmentRequest;
use App\Models\Collaborator;
use App\Repositories\Cms\Briefs\BriefAssignmentRequestRepository;
use App\Repositories\Cms\Briefs\BriefRepository;
use App\Repositories\OpenCollab\ContributorBriefRepository;
use App\Services\Cms\BriefAssignmentRequestService;
use App\Services\OpenCollab\OpenCollabBriefNotificationService;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BriefAssignmentRequestServiceTest extends TestCase
{
    private BriefAssignmentRequestRepository $requestRepository;
    private BriefRepository $briefRepository;
    private ContributorBriefRepository $contributorBriefRepository;
    private LogBriefActivity $logActivity;
    private OpenCollabBriefNotificationService $notifications;
    private Database $database;
    private BriefAssignmentRequestService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requestRepository          = Mockery::mock(BriefAssignmentRequestRepository::class);
        $this->briefRepository            = Mockery::mock(BriefRepository::class);
        $this->contributorBriefRepository = Mockery::mock(ContributorBriefRepository::class);
        $this->logActivity                = Mockery::mock(LogBriefActivity::class);
        $this->notifications              = Mockery::mock(OpenCollabBriefNotificationService::class);
        $this->database                   = Mockery::mock(Database::class);

        // Default transaction passthrough
        $this->database
            ->shouldReceive('transaction')
            ->andReturnUsing(fn(callable $cb) => $cb())
            ->byDefault();

        $this->service = new BriefAssignmentRequestService(
            $this->requestRepository,
            $this->briefRepository,
            $this->contributorBriefRepository,
            $this->logActivity,
            $this->notifications,
            $this->database,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeBrief(
        int $id = 1,
        string $status = 'draft',
        ?string $deadline = null,
        int $siteId = 1,
    ): Brief {
        $brief = Mockery::mock(Brief::class)->makePartial();
        $brief->id       = $id;
        $brief->status   = $status;
        $brief->site_id  = $siteId;
        $brief->title    = 'Test Brief';
        $brief->deadline = $deadline;
        return $brief;
    }

    private function makeAssignment(int $id = 10, string $role = 'writer'): Collaborator
    {
        $assignment = Mockery::mock(Collaborator::class)->makePartial();
        $assignment->id   = $id;
        $assignment->role = $role;
        return $assignment;
    }

    private function makeRequest(
        int $id = 100,
        string $type = 'clarification',
        string $status = 'pending',
        int $briefId = 1,
        int $contributorId = 5,
    ): BriefAssignmentRequest {
        $request = Mockery::mock(BriefAssignmentRequest::class)->makePartial();
        $request->id             = $id;
        $request->type           = $type;
        $request->status         = $status;
        $request->brief_id       = $briefId;
        $request->contributor_id = $contributorId;
        $request->shouldReceive('isPending')->andReturn($status === 'pending');
        $request->shouldReceive('isTerminal')
            ->andReturn(BriefAssignmentRequestStatus::from($status)->isTerminal());
        return $request;
    }

    private function allowActivity(): void
    {
        $this->logActivity->shouldReceive('handle')->andReturn(Mockery::mock(BriefActivityLog::class))->byDefault();
    }

    private function allowNotifications(): void
    {
        $this->notifications->shouldReceive('notifyContributor')->andReturn(null)->byDefault();
    }

    // =========================================================================
    // createClarificationRequest
    // =========================================================================

    public function test_contributor_can_create_clarification_request(): void
    {
        $brief      = $this->makeBrief();
        $assignment = $this->makeAssignment();

        $this->contributorBriefRepository
            ->shouldReceive('assignmentForBrief')
            ->once()
            ->with(1, 5, 1)
            ->andReturn($assignment);

        $expectedRequest = $this->makeRequest(100, 'clarification', 'pending');

        $this->requestRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data) {
                return $data['type']   === BriefAssignmentRequestType::Clarification->value
                    && $data['status'] === BriefAssignmentRequestStatus::Pending->value
                    && $data['message'] === 'Please clarify the scope.';
            }))
            ->andReturn($expectedRequest);

        $this->logActivity
            ->shouldReceive('handle')
            ->once()
            ->with(1, 5, 'clarification.requested', Mockery::any(), Mockery::any());

        $this->notifications
            ->shouldReceive('notifyContributor')
            ->once()
            ->with(5, $brief, 'brief.clarification_requested', Mockery::any(), Mockery::any());

        $result = $this->service->createClarificationRequest($brief, 5, 'Please clarify the scope.');

        $this->assertSame($expectedRequest, $result);
    }

    public function test_clarification_request_requires_non_empty_message(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/message.*required/i');

        $brief      = $this->makeBrief();
        $assignment = $this->makeAssignment();

        $this->contributorBriefRepository
            ->shouldReceive('assignmentForBrief')
            ->andReturn($assignment);

        $this->service->createClarificationRequest($brief, 5, '   ');
    }

    public function test_clarification_request_enforces_message_max_length(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/5000/');

        $brief      = $this->makeBrief();
        $assignment = $this->makeAssignment();

        $this->contributorBriefRepository
            ->shouldReceive('assignmentForBrief')
            ->andReturn($assignment);

        $this->service->createClarificationRequest($brief, 5, str_repeat('x', 5001));
    }

    public function test_contributor_cannot_request_clarification_for_someone_elses_brief(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no active assignment/i');

        $brief = $this->makeBrief();

        $this->contributorBriefRepository
            ->shouldReceive('assignmentForBrief')
            ->once()
            ->andReturn(null);

        $this->service->createClarificationRequest($brief, 5, 'Hello?');
    }

    public function test_clarification_request_blocked_on_archived_brief(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/archived/i');

        $brief = $this->makeBrief(status: 'archived');

        // No assignment lookup should happen — brief guard fires first.
        $this->contributorBriefRepository->shouldNotReceive('assignmentForBrief');

        $this->service->createClarificationRequest($brief, 5, 'Can I still ask?');
    }

    public function test_clarification_request_blocked_on_rejected_assignment(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/rejected/i');

        $brief      = $this->makeBrief();
        $assignment = $this->makeAssignment(role: 'rejected');

        $this->contributorBriefRepository
            ->shouldReceive('assignmentForBrief')
            ->andReturn($assignment);

        $this->service->createClarificationRequest($brief, 5, 'A question');
    }

    public function test_clarification_request_wraps_in_transaction(): void
    {
        $brief      = $this->makeBrief();
        $assignment = $this->makeAssignment();

        $this->contributorBriefRepository->shouldReceive('assignmentForBrief')->andReturn($assignment);
        $this->requestRepository->shouldReceive('create')->andReturn($this->makeRequest());
        $this->allowActivity();
        $this->allowNotifications();

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn(callable $cb) => $cb());

        $this->service->createClarificationRequest($brief, 5, 'A question');

        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // createDeadlineChangeRequest
    // =========================================================================

    public function test_contributor_can_create_deadline_change_request(): void
    {
        $brief      = $this->makeBrief();
        $assignment = $this->makeAssignment();
        $future     = (new \DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s');

        $this->contributorBriefRepository
            ->shouldReceive('assignmentForBrief')
            ->andReturn($assignment);

        $this->requestRepository
            ->shouldReceive('findPendingForAssignment')
            ->once()
            ->with(10, BriefAssignmentRequestType::DeadlineChange)
            ->andReturn(null);

        $expectedRequest = $this->makeRequest(101, 'deadline_change', 'pending');

        $this->requestRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data) {
                return $data['type']   === BriefAssignmentRequestType::DeadlineChange->value
                    && $data['status'] === BriefAssignmentRequestStatus::Pending->value
                    && isset($data['requested_deadline_at']);
            }))
            ->andReturn($expectedRequest);

        $this->logActivity
            ->shouldReceive('handle')
            ->once()
            ->with(1, 5, 'deadline_change.requested', Mockery::any(), Mockery::any());

        $this->notifications
            ->shouldReceive('notifyContributor')
            ->once()
            ->with(5, $brief, 'brief.deadline_change_requested', Mockery::any(), Mockery::any());

        $result = $this->service->createDeadlineChangeRequest($brief, 5, $future, 'Need more time.');

        $this->assertSame($expectedRequest, $result);
    }

    public function test_deadline_change_requires_reason(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/reason.*required/i');

        $brief      = $this->makeBrief();
        $assignment = $this->makeAssignment();
        $future     = (new \DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s');

        $this->contributorBriefRepository->shouldReceive('assignmentForBrief')->andReturn($assignment);

        $this->service->createDeadlineChangeRequest($brief, 5, $future, '  ');
    }

    public function test_deadline_change_rejects_past_deadline(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/future date/i');

        $brief      = $this->makeBrief();
        $assignment = $this->makeAssignment();

        $this->contributorBriefRepository->shouldReceive('assignmentForBrief')->andReturn($assignment);

        $this->service->createDeadlineChangeRequest($brief, 5, '2020-01-01 00:00:00', 'Reason');
    }

    public function test_deadline_change_must_be_later_than_current_deadline(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/later than the current deadline/i');

        $currentDeadline = (new \DateTimeImmutable('+60 days'))->format('Y-m-d H:i:s');
        $earlier         = (new \DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s');

        $brief      = $this->makeBrief(deadline: $currentDeadline);
        $assignment = $this->makeAssignment();

        $this->contributorBriefRepository->shouldReceive('assignmentForBrief')->andReturn($assignment);

        $this->service->createDeadlineChangeRequest($brief, 5, $earlier, 'Reason');
    }

    public function test_duplicate_pending_deadline_request_is_blocked(): void
    {
        $this->expectException(DuplicateActiveRequestException::class);

        $brief      = $this->makeBrief();
        $assignment = $this->makeAssignment();
        $future     = (new \DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s');

        $this->contributorBriefRepository->shouldReceive('assignmentForBrief')->andReturn($assignment);

        $this->requestRepository
            ->shouldReceive('findPendingForAssignment')
            ->andReturn($this->makeRequest(99, 'deadline_change', 'pending'));

        $this->service->createDeadlineChangeRequest($brief, 5, $future, 'Reason');
    }

    // =========================================================================
    // createNegotiationRequest
    // =========================================================================

    public function test_contributor_can_create_negotiation_request(): void
    {
        $brief      = $this->makeBrief();
        $assignment = $this->makeAssignment();

        $this->contributorBriefRepository->shouldReceive('assignmentForBrief')->andReturn($assignment);

        $expectedRequest = $this->makeRequest(102, 'negotiation', 'pending');

        $this->requestRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data) {
                return $data['type']   === BriefAssignmentRequestType::Negotiation->value
                    && $data['message'] === 'I need a larger scope.';
            }))
            ->andReturn($expectedRequest);

        $this->logActivity
            ->shouldReceive('handle')
            ->once()
            ->with(1, 5, 'negotiation.requested', Mockery::any(), Mockery::any());

        $this->notifications
            ->shouldReceive('notifyContributor')
            ->once()
            ->with(5, $brief, 'brief.negotiation_requested', Mockery::any(), Mockery::any());

        $result = $this->service->createNegotiationRequest($brief, 5, 'I need a larger scope.');

        $this->assertSame($expectedRequest, $result);
    }

    public function test_negotiation_request_requires_message(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/message.*required/i');

        $brief      = $this->makeBrief();
        $assignment = $this->makeAssignment();

        $this->contributorBriefRepository->shouldReceive('assignmentForBrief')->andReturn($assignment);

        $this->service->createNegotiationRequest($brief, 5, '');
    }

    public function test_negotiation_request_rejects_past_deadline_when_supplied(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/future date/i');

        $brief      = $this->makeBrief();
        $assignment = $this->makeAssignment();

        $this->contributorBriefRepository->shouldReceive('assignmentForBrief')->andReturn($assignment);

        $this->service->createNegotiationRequest(
            $brief, 5, 'I need changes.', '2020-01-01',
        );
    }

    // =========================================================================
    // recordRejectionReason
    // =========================================================================

    public function test_contributor_rejection_stores_structured_reason(): void
    {
        $brief      = $this->makeBrief();
        $assignment = $this->makeAssignment();

        $this->contributorBriefRepository->shouldReceive('assignmentForBrief')->andReturn($assignment);

        $expectedRequest = $this->makeRequest(103, 'rejection', 'resolved');

        $this->requestRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data) {
                return $data['type']   === BriefAssignmentRequestType::Rejection->value
                    && $data['status'] === BriefAssignmentRequestStatus::Resolved->value
                    && $data['reason'] === 'Not a good fit.';
            }))
            ->andReturn($expectedRequest);

        $this->logActivity
            ->shouldReceive('handle')
            ->once()
            ->with(1, 5, 'assignment.rejected', Mockery::any(), Mockery::any());

        $this->notifications
            ->shouldReceive('notifyContributor')
            ->once()
            ->with(5, $brief, 'brief.assignment_rejected', Mockery::any(), Mockery::any());

        $result = $this->service->recordRejectionReason($brief, 5, 'Not a good fit.');

        $this->assertSame($expectedRequest, $result);
    }

    public function test_rejection_requires_non_empty_reason(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/reason.*required/i');

        $brief      = $this->makeBrief();
        $assignment = $this->makeAssignment();

        $this->contributorBriefRepository->shouldReceive('assignmentForBrief')->andReturn($assignment);

        $this->service->recordRejectionReason($brief, 5, '');
    }

    // =========================================================================
    // approveDeadlineChangeRequest
    // =========================================================================

    public function test_approving_deadline_request_updates_brief_deadline(): void
    {
        $future  = (new \DateTimeImmutable('+30 days'));
        $request = $this->makeRequest(100, 'deadline_change', 'pending');
        $request->requested_deadline_at = $future;

        $brief = $this->makeBrief();

        $this->briefRepository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($brief);

        $this->briefRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(fn(array $data) => isset($data['deadline'])));

        $resolvedRequest = $this->makeRequest(100, 'deadline_change', 'approved');

        $this->requestRepository
            ->shouldReceive('resolve')
            ->once()
            ->with($request, BriefAssignmentRequestStatus::Approved, 99, null)
            ->andReturn($resolvedRequest);

        $this->logActivity
            ->shouldReceive('handle')
            ->once()
            ->with(1, 99, 'deadline_change.approved', Mockery::any(), Mockery::any());

        $this->notifications
            ->shouldReceive('notifyContributor')
            ->once()
            ->with(5, $brief, 'brief.deadline_change_approved', Mockery::any(), Mockery::any());

        $result = $this->service->approveDeadlineChangeRequest($request, editorId: 99);

        $this->assertSame($resolvedRequest, $result);
    }

    public function test_rejecting_deadline_request_does_not_update_brief_deadline(): void
    {
        $request = $this->makeRequest(100, 'deadline_change', 'pending');
        $request->requested_deadline_at = new \DateTimeImmutable('+30 days');

        $brief = $this->makeBrief();

        $this->briefRepository->shouldReceive('find')->andReturn($brief);

        // Deadline must NOT be updated.
        $this->briefRepository->shouldNotReceive('update');

        $resolvedRequest = $this->makeRequest(100, 'deadline_change', 'rejected');

        $this->requestRepository
            ->shouldReceive('resolve')
            ->once()
            ->with($request, BriefAssignmentRequestStatus::Rejected, 99, 'Not valid.')
            ->andReturn($resolvedRequest);

        $this->logActivity->shouldReceive('handle')->once();
        $this->notifications->shouldReceive('notifyContributor')->once();

        $result = $this->service->rejectDeadlineChangeRequest($request, 99, 'Not valid.');

        $this->assertSame($resolvedRequest, $result);
    }

    public function test_approving_already_resolved_request_throws(): void
    {
        $this->expectException(BriefAssignmentRequestAlreadyResolvedException::class);

        $request = $this->makeRequest(100, 'deadline_change', 'approved');

        $this->service->approveDeadlineChangeRequest($request, 99);
    }

    public function test_approving_wrong_request_type_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/deadline_change/");

        $request = $this->makeRequest(100, 'clarification', 'pending');

        $this->service->approveDeadlineChangeRequest($request, 99);
    }

    // =========================================================================
    // approveNegotiationRequest / rejectNegotiationRequest
    // =========================================================================

    public function test_negotiation_approval_records_editor_response(): void
    {
        $request = $this->makeRequest(100, 'negotiation', 'pending');
        $brief   = $this->makeBrief();

        $this->briefRepository->shouldReceive('find')->andReturn($brief);

        $resolvedRequest = $this->makeRequest(100, 'negotiation', 'approved');

        $this->requestRepository
            ->shouldReceive('resolve')
            ->once()
            ->with($request, BriefAssignmentRequestStatus::Approved, 99, 'Approved!')
            ->andReturn($resolvedRequest);

        $this->logActivity->shouldReceive('handle')->once()
            ->with(1, 99, 'negotiation.approved', Mockery::any(), Mockery::any());

        $this->notifications->shouldReceive('notifyContributor')->once()
            ->with(5, $brief, 'brief.negotiation_approved', Mockery::any(), Mockery::any());

        $result = $this->service->approveNegotiationRequest($request, 99, 'Approved!');

        $this->assertSame($resolvedRequest, $result);
    }

    public function test_negotiation_rejection_records_editor_response(): void
    {
        $request = $this->makeRequest(100, 'negotiation', 'pending');
        $brief   = $this->makeBrief();

        $this->briefRepository->shouldReceive('find')->andReturn($brief);

        $resolvedRequest = $this->makeRequest(100, 'negotiation', 'rejected');

        $this->requestRepository
            ->shouldReceive('resolve')
            ->once()
            ->with($request, BriefAssignmentRequestStatus::Rejected, 99, 'Scope too large.')
            ->andReturn($resolvedRequest);

        $this->logActivity->shouldReceive('handle')->once()
            ->with(1, 99, 'negotiation.rejected', Mockery::any(), Mockery::any());

        $this->notifications->shouldReceive('notifyContributor')->once()
            ->with(5, $brief, 'brief.negotiation_rejected', Mockery::any(), Mockery::any());

        $result = $this->service->rejectNegotiationRequest($request, 99, 'Scope too large.');

        $this->assertSame($resolvedRequest, $result);
    }

    // =========================================================================
    // respondToClarificationRequest
    // =========================================================================

    public function test_clarification_response_is_contributor_visible(): void
    {
        $request = $this->makeRequest(100, 'clarification', 'pending');
        $brief   = $this->makeBrief();

        $this->briefRepository->shouldReceive('find')->andReturn($brief);

        $resolvedRequest = $this->makeRequest(100, 'clarification', 'resolved');

        $this->requestRepository
            ->shouldReceive('resolve')
            ->once()
            ->with($request, BriefAssignmentRequestStatus::Resolved, 99, 'Here is the clarification.')
            ->andReturn($resolvedRequest);

        $this->logActivity->shouldReceive('handle')->once()
            ->with(1, 99, 'clarification.responded', Mockery::any(), Mockery::any());

        $this->notifications->shouldReceive('notifyContributor')->once()
            ->with(5, $brief, 'brief.clarification_responded', Mockery::any(), Mockery::any());

        $result = $this->service->respondToClarificationRequest($request, 99, 'Here is the clarification.');

        $this->assertSame($resolvedRequest, $result);
    }

    public function test_clarification_response_requires_non_empty_content(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/response.*required/i');

        $request = $this->makeRequest(100, 'clarification', 'pending');

        $this->service->respondToClarificationRequest($request, 99, '   ');
    }

    public function test_cannot_respond_to_already_resolved_clarification(): void
    {
        $this->expectException(BriefAssignmentRequestAlreadyResolvedException::class);

        $request = $this->makeRequest(100, 'clarification', 'resolved');

        $this->service->respondToClarificationRequest($request, 99, 'Late response');
    }

    // =========================================================================
    // cancelRequest
    // =========================================================================

    public function test_cancel_request_marks_as_cancelled(): void
    {
        $request         = $this->makeRequest(100, 'clarification', 'pending');
        $cancelledRequest = $this->makeRequest(100, 'clarification', 'cancelled');

        $this->requestRepository
            ->shouldReceive('resolve')
            ->once()
            ->with($request, BriefAssignmentRequestStatus::Cancelled, 5, null)
            ->andReturn($cancelledRequest);

        $this->logActivity
            ->shouldReceive('handle')
            ->once()
            ->with(1, 5, 'request.cancelled', Mockery::any(), Mockery::any());

        $result = $this->service->cancelRequest($request, actorId: 5);

        $this->assertSame($cancelledRequest, $result);
    }

    public function test_cannot_cancel_already_resolved_request(): void
    {
        $this->expectException(BriefAssignmentRequestAlreadyResolvedException::class);

        $request = $this->makeRequest(100, 'clarification', 'approved');

        $this->service->cancelRequest($request, 5);
    }

    // =========================================================================
    // Transaction enforcement
    // =========================================================================

    public function test_approve_deadline_change_wraps_in_transaction(): void
    {
        $future  = new \DateTimeImmutable('+30 days');
        $request = $this->makeRequest(100, 'deadline_change', 'pending');
        $request->requested_deadline_at = $future;

        $brief = $this->makeBrief();
        $this->briefRepository->shouldReceive('find')->andReturn($brief);
        $this->briefRepository->shouldReceive('update')->andReturn(null);

        $resolved = $this->makeRequest(100, 'deadline_change', 'approved');
        $this->requestRepository->shouldReceive('resolve')->andReturn($resolved);
        $this->allowActivity();
        $this->allowNotifications();

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn(callable $cb) => $cb());

        $this->service->approveDeadlineChangeRequest($request, 99);

        $this->addToAssertionCount(1);
    }

    public function test_reject_deadline_change_wraps_in_transaction(): void
    {
        $request = $this->makeRequest(100, 'deadline_change', 'pending');
        $request->requested_deadline_at = new \DateTimeImmutable('+30 days');

        $brief = $this->makeBrief();
        $this->briefRepository->shouldReceive('find')->andReturn($brief);

        $resolved = $this->makeRequest(100, 'deadline_change', 'rejected');
        $this->requestRepository->shouldReceive('resolve')->andReturn($resolved);
        $this->allowActivity();
        $this->allowNotifications();

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn(callable $cb) => $cb());

        $this->service->rejectDeadlineChangeRequest($request, 99);

        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // Internal CMS fields not exposed to OpenCollab
    // =========================================================================

    public function test_to_contributor_array_omits_resolved_by_and_metadata(): void
    {
        $request = Mockery::mock(BriefAssignmentRequest::class)->makePartial();
        $request->id             = 1;
        $request->type           = 'clarification';
        $request->status         = 'resolved';
        $request->message        = 'Hello';
        $request->reason         = null;
        $request->scope_details  = null;
        $request->editor_response = 'Here is the answer.';
        $request->contributor_id = 5;
        $request->brief_id       = 1;
        $request->resolved_by    = 99;   // CMS internal
        $request->metadata       = ['internal_note' => 'Do not share'];  // CMS internal
        $request->requested_deadline_at = null;
        $request->created_at     = new \DateTimeImmutable('2026-01-01');

        $safeData = $request->toContributorArray();

        $this->assertArrayNotHasKey('resolved_by', $safeData);
        $this->assertArrayNotHasKey('metadata', $safeData);
        $this->assertArrayHasKey('editor_response', $safeData);
        $this->assertEquals('Here is the answer.', $safeData['editor_response']);
    }
}
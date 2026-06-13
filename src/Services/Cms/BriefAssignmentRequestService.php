<?php

namespace App\Services\Cms;

use App\Actions\Brief\LogBriefActivity;
use App\Enums\OpenCollab\BriefAssignmentRequestStatus;
use App\Enums\OpenCollab\BriefAssignmentRequestType;
use App\Exceptions\OpenCollab\BriefAssignmentRequestAlreadyResolvedException;
use App\Exceptions\OpenCollab\DuplicateActiveRequestException;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Brief;
use App\Models\BriefAssignmentRequest;
use App\Models\Collaborator;
use App\Repositories\Cms\Briefs\BriefAssignmentRequestRepository;
use App\Repositories\Cms\Briefs\BriefRepository;
use App\Repositories\OpenCollab\ContributorBriefRepository;
use App\Services\OpenCollab\OpenCollabBriefNotificationService;
use InvalidArgumentException;
use RuntimeException;

class BriefAssignmentRequestService
{
    public function __construct(
        private readonly BriefAssignmentRequestRepository  $requestRepository,
        private readonly BriefRepository                   $briefRepository,
        private readonly ContributorBriefRepository        $contributorBriefRepository,
        private readonly LogBriefActivity                  $logActivity,
        private readonly OpenCollabBriefNotificationService $notifications,
        private readonly Database                          $database,
    ) {}

    // -------------------------------------------------------------------------
    // Contributor-initiated creation
    // -------------------------------------------------------------------------

    public function createClarificationRequest(
        Brief $brief,
        int $contributorId,
        string $message,
    ): BriefAssignmentRequest {
        $this->assertBriefAcceptsRequests($brief);
        $assignment = $this->requireAssignment($brief, $contributorId);
        $this->assertAssignmentIsActive($assignment);

        if (empty(trim($message))) {
            throw new InvalidArgumentException('Message is required for clarification requests.');
        }

        if (strlen($message) > 5000) {
            throw new InvalidArgumentException('Message must not exceed 5000 characters.');
        }

        return $this->database->transaction(function () use ($brief, $assignment, $contributorId, $message) {
            $request = $this->requestRepository->create([
                'brief_id'       => $brief->id,
                'assignment_id'  => $assignment->id,
                'contributor_id' => $contributorId,
                'type'           => BriefAssignmentRequestType::Clarification->value,
                'status'         => BriefAssignmentRequestStatus::Pending->value,
                'message'        => $message,
            ]);

            $this->logActivity->handle(
                $brief->id,
                $contributorId,
                'clarification.requested',
                'Clarification requested',
                ['request_id' => $request->id, 'contributor_visible' => true],
            );

            $this->notifications->notifyContributor(
                $contributorId,
                $brief,
                'brief.clarification_requested',
                'Clarification requested',
                "Your clarification request was sent for {$brief->title}.",
            );

            return $request;
        });
    }

    public function createDeadlineChangeRequest(
        Brief $brief,
        int $contributorId,
        string $requestedDeadline,
        string $reason,
    ): BriefAssignmentRequest {
        $this->assertBriefAcceptsRequests($brief);
        $assignment = $this->requireAssignment($brief, $contributorId);
        $this->assertAssignmentIsActive($assignment);

        if (empty(trim($reason))) {
            throw new InvalidArgumentException('Reason is required for deadline change requests.');
        }

        if (strlen($reason) > 2000) {
            throw new InvalidArgumentException('Reason must not exceed 2000 characters.');
        }

        $parsedDeadline = $this->parseAndValidateFutureDeadline($requestedDeadline);
        $this->assertRequestedDeadlineIsLaterThanCurrent($brief, $parsedDeadline);

        $existing = $this->requestRepository->findPendingForAssignment(
            $assignment->id,
            BriefAssignmentRequestType::DeadlineChange,
        );

        if ($existing !== null) {
            throw new DuplicateActiveRequestException(
                'A pending deadline change request already exists for this assignment.',
            );
        }

        return $this->database->transaction(function () use ($brief, $assignment, $contributorId, $parsedDeadline, $reason) {
            $request = $this->requestRepository->create([
                'brief_id'              => $brief->id,
                'assignment_id'         => $assignment->id,
                'contributor_id'        => $contributorId,
                'type'                  => BriefAssignmentRequestType::DeadlineChange->value,
                'status'                => BriefAssignmentRequestStatus::Pending->value,
                'reason'                => $reason,
                'requested_deadline_at' => $parsedDeadline,
            ]);

            $this->logActivity->handle(
                $brief->id,
                $contributorId,
                'deadline_change.requested',
                'Deadline change requested',
                ['request_id' => $request->id, 'contributor_visible' => true],
            );

            $this->notifications->notifyContributor(
                $contributorId,
                $brief,
                'brief.deadline_change_requested',
                'Deadline change requested',
                "Your deadline change request was sent for {$brief->title}.",
            );

            return $request;
        });
    }

    public function createNegotiationRequest(
        Brief $brief,
        int $contributorId,
        string $message,
        ?string $requestedDeadline = null,
        ?string $scopeDetails = null,
    ): BriefAssignmentRequest {
        $this->assertBriefAcceptsRequests($brief);
        $assignment = $this->requireAssignment($brief, $contributorId);
        $this->assertAssignmentIsActive($assignment);

        if (empty(trim($message))) {
            throw new InvalidArgumentException('Message is required for negotiation requests.');
        }

        if (strlen($message) > 5000) {
            throw new InvalidArgumentException('Message must not exceed 5000 characters.');
        }

        if ($scopeDetails !== null && strlen($scopeDetails) > 5000) {
            throw new InvalidArgumentException('Scope details must not exceed 5000 characters.');
        }

        $parsedDeadline = null;
        if ($requestedDeadline !== null) {
            $parsedDeadline = $this->parseAndValidateFutureDeadline($requestedDeadline);
        }

        return $this->database->transaction(function () use ($brief, $assignment, $contributorId, $message, $parsedDeadline, $scopeDetails) {
            $request = $this->requestRepository->create([
                'brief_id'              => $brief->id,
                'assignment_id'         => $assignment->id,
                'contributor_id'        => $contributorId,
                'type'                  => BriefAssignmentRequestType::Negotiation->value,
                'status'                => BriefAssignmentRequestStatus::Pending->value,
                'message'               => $message,
                'requested_deadline_at' => $parsedDeadline,
                'scope_details'         => $scopeDetails,
            ]);

            $this->logActivity->handle(
                $brief->id,
                $contributorId,
                'negotiation.requested',
                'Negotiation requested',
                ['request_id' => $request->id, 'contributor_visible' => true],
            );

            $this->notifications->notifyContributor(
                $contributorId,
                $brief,
                'brief.negotiation_requested',
                'Negotiation requested',
                "Your negotiation request was sent for {$brief->title}.",
            );

            return $request;
        });
    }

    public function recordRejectionReason(
        Brief $brief,
        int $contributorId,
        string $reason,
    ): BriefAssignmentRequest {
        $this->assertBriefAcceptsRequests($brief);
        $assignment = $this->requireAssignment($brief, $contributorId);
        $this->assertAssignmentIsActive($assignment);

        if (empty(trim($reason))) {
            throw new InvalidArgumentException('Reason is required when rejecting an assignment.');
        }

        if (strlen($reason) > 2000) {
            throw new InvalidArgumentException('Reason must not exceed 2000 characters.');
        }

        return $this->database->transaction(function () use ($brief, $assignment, $contributorId, $reason) {
            $request = $this->requestRepository->create([
                'brief_id'       => $brief->id,
                'assignment_id'  => $assignment->id,
                'contributor_id' => $contributorId,
                'type'           => BriefAssignmentRequestType::Rejection->value,
                'status'         => BriefAssignmentRequestStatus::Resolved->value,
                'reason'         => $reason,
                'resolved_by'    => $contributorId,
                'resolved_at'    => now(),
            ]);

            $this->logActivity->handle(
                $brief->id,
                $contributorId,
                'assignment.rejected',
                'Assignment rejected',
                ['request_id' => $request->id, 'contributor_visible' => true],
            );

            $this->notifications->notifyContributor(
                $contributorId,
                $brief,
                'brief.assignment_rejected',
                'Assignment rejected',
                "You rejected the assignment for {$brief->title}.",
            );

            return $request;
        });
    }

    // -------------------------------------------------------------------------
    // CMS editor resolution
    // -------------------------------------------------------------------------

    public function approveDeadlineChangeRequest(
        BriefAssignmentRequest $request,
        int $editorId,
        ?string $editorResponse = null,
    ): BriefAssignmentRequest {
        $this->assertTypeIs($request, BriefAssignmentRequestType::DeadlineChange);
        $this->assertRequestIsPending($request);

        return $this->database->transaction(function () use ($request, $editorId, $editorResponse) {
            $brief = $this->briefRepository->find((int) $request->brief_id);

            if (!$brief) {
                throw new RuntimeException('Brief not found for request.');
            }

            // Re-validate the deadline is still in the future and still later than current.
            if ($request->requested_deadline_at !== null) {
                $this->assertRequestedDeadlineIsLaterThanCurrent($brief, $request->requested_deadline_at);
            }

            // Apply the new deadline to the brief.
            if ($request->requested_deadline_at !== null) {
                $this->briefRepository->update((int) $brief->id, [
                    'deadline' => $request->requested_deadline_at,
                ]);
            }

            $resolved = $this->requestRepository->resolve(
                $request,
                BriefAssignmentRequestStatus::Approved,
                $editorId,
                $editorResponse,
            );

            $this->logActivity->handle(
                (int) $request->brief_id,
                $editorId,
                'deadline_change.approved',
                'Deadline change approved',
                ['request_id' => $request->id, 'contributor_visible' => true],
            );

            $this->notifications->notifyContributor(
                (int) $request->contributor_id,
                $brief,
                'brief.deadline_change_approved',
                'Deadline change approved',
                "Your deadline change request for {$brief->title} was approved.",
            );

            return $resolved;
        });
    }

    public function rejectDeadlineChangeRequest(
        BriefAssignmentRequest $request,
        int $editorId,
        ?string $editorResponse = null,
    ): BriefAssignmentRequest {
        $this->assertTypeIs($request, BriefAssignmentRequestType::DeadlineChange);
        $this->assertRequestIsPending($request);

        return $this->database->transaction(function () use ($request, $editorId, $editorResponse) {
            $resolved = $this->requestRepository->resolve(
                $request,
                BriefAssignmentRequestStatus::Rejected,
                $editorId,
                $editorResponse,
            );

            $brief = $this->briefRepository->find((int) $request->brief_id);

            $this->logActivity->handle(
                (int) $request->brief_id,
                $editorId,
                'deadline_change.rejected',
                'Deadline change rejected',
                ['request_id' => $request->id, 'contributor_visible' => true],
            );

            if ($brief) {
                $this->notifications->notifyContributor(
                    (int) $request->contributor_id,
                    $brief,
                    'brief.deadline_change_rejected',
                    'Deadline change rejected',
                    "Your deadline change request for {$brief->title} was not approved.",
                );
            }

            return $resolved;
        });
    }

    public function approveNegotiationRequest(
        BriefAssignmentRequest $request,
        int $editorId,
        ?string $editorResponse = null,
    ): BriefAssignmentRequest {
        $this->assertTypeIs($request, BriefAssignmentRequestType::Negotiation);
        $this->assertRequestIsPending($request);

        return $this->database->transaction(function () use ($request, $editorId, $editorResponse) {
            $resolved = $this->requestRepository->resolve(
                $request,
                BriefAssignmentRequestStatus::Approved,
                $editorId,
                $editorResponse,
            );

            $brief = $this->briefRepository->find((int) $request->brief_id);

            $this->logActivity->handle(
                (int) $request->brief_id,
                $editorId,
                'negotiation.approved',
                'Negotiation approved',
                ['request_id' => $request->id, 'contributor_visible' => true],
            );

            if ($brief) {
                $this->notifications->notifyContributor(
                    (int) $request->contributor_id,
                    $brief,
                    'brief.negotiation_approved',
                    'Negotiation approved',
                    "Your negotiation request for {$brief->title} was approved.",
                );
            }

            return $resolved;
        });
    }

    public function rejectNegotiationRequest(
        BriefAssignmentRequest $request,
        int $editorId,
        ?string $editorResponse = null,
    ): BriefAssignmentRequest {
        $this->assertTypeIs($request, BriefAssignmentRequestType::Negotiation);
        $this->assertRequestIsPending($request);

        return $this->database->transaction(function () use ($request, $editorId, $editorResponse) {
            $resolved = $this->requestRepository->resolve(
                $request,
                BriefAssignmentRequestStatus::Rejected,
                $editorId,
                $editorResponse,
            );

            $brief = $this->briefRepository->find((int) $request->brief_id);

            $this->logActivity->handle(
                (int) $request->brief_id,
                $editorId,
                'negotiation.rejected',
                'Negotiation rejected',
                ['request_id' => $request->id, 'contributor_visible' => true],
            );

            if ($brief) {
                $this->notifications->notifyContributor(
                    (int) $request->contributor_id,
                    $brief,
                    'brief.negotiation_rejected',
                    'Negotiation rejected',
                    "Your negotiation request for {$brief->title} was not approved.",
                );
            }

            return $resolved;
        });
    }

    public function respondToClarificationRequest(
        BriefAssignmentRequest $request,
        int $editorId,
        string $editorResponse,
    ): BriefAssignmentRequest {
        $this->assertTypeIs($request, BriefAssignmentRequestType::Clarification);
        $this->assertRequestIsPending($request);

        if (empty(trim($editorResponse))) {
            throw new InvalidArgumentException('A response is required when responding to a clarification request.');
        }

        return $this->database->transaction(function () use ($request, $editorId, $editorResponse) {
            $resolved = $this->requestRepository->resolve(
                $request,
                BriefAssignmentRequestStatus::Resolved,
                $editorId,
                $editorResponse,
            );

            $brief = $this->briefRepository->find((int) $request->brief_id);

            $this->logActivity->handle(
                (int) $request->brief_id,
                $editorId,
                'clarification.responded',
                'Clarification response provided',
                ['request_id' => $request->id, 'contributor_visible' => true],
            );

            if ($brief) {
                $this->notifications->notifyContributor(
                    (int) $request->contributor_id,
                    $brief,
                    'brief.clarification_responded',
                    'Clarification response received',
                    "Your clarification request for {$brief->title} has been answered.",
                );
            }

            return $resolved;
        });
    }

    public function cancelRequest(
        BriefAssignmentRequest $request,
        int $actorId,
    ): BriefAssignmentRequest {
        $this->assertRequestIsPending($request);

        return $this->database->transaction(function () use ($request, $actorId) {
            $resolved = $this->requestRepository->resolve(
                $request,
                BriefAssignmentRequestStatus::Cancelled,
                $actorId,
                null,
            );

            $this->logActivity->handle(
                (int) $request->brief_id,
                $actorId,
                "request.cancelled",
                'Request cancelled',
                ['request_id' => $request->id, 'contributor_visible' => false],
            );

            return $resolved;
        });
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    public function getPendingRequestsForBrief(int $briefId): Collection
    {
        return $this->requestRepository->findPendingForBrief($briefId);
    }

    public function getAllRequestsForBrief(int $briefId): Collection
    {
        return $this->requestRepository->findForBrief($briefId);
    }

    public function getContributorVisibleRequestsForBrief(
        int $briefId,
        int $contributorId,
    ): Collection {
        return $this->requestRepository->findForContributor($contributorId, $briefId);
    }

    public function findRequestById(int $id): ?BriefAssignmentRequest
    {
        return $this->requestRepository->find($id);
    }

    // -------------------------------------------------------------------------
    // Guards
    // -------------------------------------------------------------------------

    private function assertBriefAcceptsRequests(Brief $brief): void
    {
        $blockedStatuses = ['archived', 'converted'];

        if (in_array($brief->status, $blockedStatuses, true)) {
            throw new RuntimeException(
                "Brief is {$brief->status} and cannot accept new assignment requests.",
            );
        }
    }

    private function requireAssignment(Brief $brief, int $contributorId): Collaborator
    {
        $siteId = (int) $brief->site_id;
        $assignment = $this->contributorBriefRepository->assignmentForBrief(
            (int) $brief->id,
            $contributorId,
            $siteId,
        );

        if ($assignment === null) {
            throw new RuntimeException(
                'No active assignment found for this contributor on this brief.',
            );
        }

        return $assignment;
    }

    private function assertAssignmentIsActive(Collaborator $assignment): void
    {
        $terminalRoles = ['rejected'];

        if (in_array($assignment->role, $terminalRoles, true)) {
            throw new RuntimeException(
                'Assignment has been rejected; no further actions are permitted.',
            );
        }
    }

    private function assertRequestIsPending(BriefAssignmentRequest $request): void
    {
        if ($request->isTerminal()) {
            throw new BriefAssignmentRequestAlreadyResolvedException(
                "Request #{$request->id} has already been resolved (status: {$request->status}).",
            );
        }
    }

    private function assertTypeIs(
        BriefAssignmentRequest $request,
        BriefAssignmentRequestType $expected,
    ): void {
        if ($request->type !== $expected->value) {
            throw new InvalidArgumentException(
                "Expected request type '{$expected->value}' but got '{$request->type}'.",
            );
        }
    }

    private function parseAndValidateFutureDeadline(string $deadline): \DateTimeImmutable
    {
        try {
            $parsed = new \DateTimeImmutable($deadline);
        } catch (\Throwable) {
            throw new InvalidArgumentException("Invalid deadline date: '{$deadline}'.");
        }

        if ($parsed <= new \DateTimeImmutable()) {
            throw new InvalidArgumentException('Requested deadline must be a future date.');
        }

        return $parsed;
    }

    private function assertRequestedDeadlineIsLaterThanCurrent(
        Brief $brief,
        \DateTimeInterface $requestedDeadline,
    ): void {
        // Retrieve the brief's current deadline if one exists.
        $currentDeadline = $brief->deadline ?? null;

        if ($currentDeadline === null) {
            return;
        }

        $current = $currentDeadline instanceof \DateTimeInterface
            ? $currentDeadline
            : new \DateTimeImmutable((string) $currentDeadline);

        if ($requestedDeadline <= $current) {
            throw new InvalidArgumentException(
                'Requested deadline must be later than the current deadline.',
            );
        }
    }
}
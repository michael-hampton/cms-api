<?php

namespace App\Services\OpenCollab;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\ContributorRequest;
use App\Repositories\OpenCollab\ContributorRequestRepository;
use App\Repositories\OpenCollab\InvitationRepository;

/**
 * Handles contributor self-service registration requests.
 *
 * When Site::require_invite_approval is FALSE:
 *   - Immediately creates and dispatches an invitation via InvitationService.
 *
 * When Site::require_invite_approval is TRUE:
 *   - Saves the request for admin review.
 *   - Admin approves via the admin panel, which then triggers InvitationService::create().
 *
 * Duplicate guard: a pending request or live invitation for the same email
 * on the same site is rejected.
 */
class ContributorRequestService
{
    public function __construct(
        private readonly ContributorRequestRepository $requestRepository,
        private readonly InvitationRepository         $invitationRepository,
        private readonly InvitationService            $invitationService,
        private readonly Database                     $database,
        private readonly Logger                       $logger,
    )
    {
    }

    /**
     * Submit a contributor access request.
     *
     * Returns an array with keys:
     *   requires_approval — bool
     *   request           — ContributorRequest|null (when queued for review)
     *   invitation        — Invitation|null (when auto-dispatched)
     *
     * @throws \InvalidArgumentException on duplicate or validation failure
     */
    public function submit(
        string $email,
        string $name,
        string $bio,
        int    $siteId,
        bool   $requiresApproval,
    ): array
    {
        // Guard: no pending request for this email
        if ($this->requestRepository->hasPendingRequest($email, $siteId)) {
            throw new \InvalidArgumentException(
                'A request from this email is already pending review.'
            );
        }

        // Guard: no live invitation already
        if ($this->invitationRepository->hasPendingInviteForEmail($email, $siteId)) {
            throw new \InvalidArgumentException(
                'An invitation for this email already exists. Check your inbox or request a resend.'
            );
        }

        if ($requiresApproval) {
            $request = $this->requestRepository->create([
                'site_id' => $siteId,
                'email' => $email,
                'name' => $name,
                'bio' => $bio,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $this->logger->info('Contributor access request queued for review.', [
                'email' => $email,
                'site_id' => $siteId,
            ]);

            return ['requires_approval' => true, 'request' => $request, 'invitation' => null];
        }

        // Auto-dispatch invitation immediately.
        // System user ID 0 indicates a self-service invite (no specific admin actor).
        $invitation = $this->invitationService->create(
            email: $email,
            invitedBy: 0,
            siteId: $siteId,
        );

        $this->logger->info('Contributor access request auto-approved — invitation dispatched.', [
            'email' => $email,
            'site_id' => $siteId,
        ]);

        return ['requires_approval' => false, 'request' => null, 'invitation' => $invitation];
    }

    /**
     * Admin approves a pending request and dispatches an invitation.
     *
     * @throws \InvalidArgumentException if the request is not found or not pending
     */
    public function approve(int $requestId, int $adminId): \App\Models\Invitation
    {
        $request = $this->requestRepository->find($requestId);

        if (!$request) {
            throw new \InvalidArgumentException("Request [{$requestId}] not found.");
        }

        if ($request->status !== 'pending') {
            throw new \InvalidArgumentException(
                "Request [{$requestId}] is not pending (status: {$request->status})."
            );
        }

        return $this->database->transaction(function () use ($request, $adminId): \App\Models\Invitation {
            $invitation = $this->invitationService->create(
                email: $request->email,
                invitedBy: $adminId,
                siteId: $request->site_id,
            );

            $this->requestRepository->update($request->id, [
                'status' => 'approved',
                'reviewed_by' => $adminId,
                'reviewed_at' => date('Y-m-d H:i:s'),
            ]);

            return $invitation;
        });
    }

    /**
     * Admin rejects a pending request.
     */
    public function reject(int $requestId, int $adminId, ?string $reason = null): ContributorRequest
    {
        $request = $this->requestRepository->find($requestId);

        if (!$request) {
            throw new \InvalidArgumentException("Request [{$requestId}] not found.");
        }

        if ($request->status !== 'pending') {
            throw new \InvalidArgumentException(
                "Request [{$requestId}] is not pending."
            );
        }

        $this->requestRepository->update($request->id, [
            'status' => 'rejected',
            'reviewed_by' => $adminId,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'rejection_reason' => $reason,
        ]);

        return $this->requestRepository->find($requestId);
    }

    /**
     * All pending requests for a site (admin queue).
     */
    public function pendingForSite(int $siteId): \App\Framework\Support\Collection
    {
        return $this->requestRepository->pendingForSite($siteId);
    }
}
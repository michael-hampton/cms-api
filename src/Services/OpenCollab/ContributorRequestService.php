<?php

namespace App\Services\OpenCollab;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\ContributorRequest;
use App\Repositories\OpenCollab\ContributorRequestRepository;
use App\Repositories\OpenCollab\InvitationRepository;
use App\Services\User\UserLifecycleServiceInterface;

/**
 * Handles contributor self-service registration requests.
 *
 * Site-access semantics
 * ---------------------
 * Contributor accounts are GLOBAL. A contributor can belong to multiple sites.
 * Therefore duplicate-checks are always scoped to (email, site_id).
 *
 * When Site::require_invite_approval is FALSE:
 *   - Immediately creates and dispatches an invitation via InvitationService.
 *   - The ContributorRequest record is still persisted so submitted data is
 *     not lost (prefill, onboarding, audit trail).
 *
 * When Site::require_invite_approval is TRUE:
 *   - Saves the request for admin review.
 *   - Admin approves via the admin panel, which then triggers InvitationService::create().
 *
 * Duplicate guards (all scoped to site)
 * ---------------------------------------
 *   - Existing contributor access to the site  → rejected
 *   - Pending request for the same email+site  → rejected
 *   - Pending invitation for the same email+site → rejected
 */
class ContributorRequestService
{
    public function __construct(
        private readonly ContributorRequestRepository $requestRepository,
        private readonly InvitationRepository         $invitationRepository,
        private readonly UserLifecycleServiceInterface $userLifecycle,
        private readonly OpenCollabAuthorisationInterface $authorisation,
        private readonly InvitationService            $invitationService,
        private readonly Database                     $database,
        private readonly Logger                       $logger,
    ) {
    }

    /**
     * Submit a contributor access request.
     *
     * Returns an array with keys:
     *   requires_approval — bool
     *   request           — ContributorRequest (always persisted now)
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
        array $customFields = [],
    ): array {
        $email = $this->normaliseEmail($email);

        return $this->database->transaction(function () use (
            $email,
            $name,
            $bio,
            $siteId,
            $requiresApproval,
            $customFields
        ): array {
            if ($this->emailAlreadyHasSiteAccess($email, $siteId)) {
                throw new \InvalidArgumentException(
                    'This email already has contributor access to this site.'
                );
            }

            if ($this->requestRepository->hasPendingRequest($email, $siteId)) {
                throw new \InvalidArgumentException(
                    'A request from this email is already pending review.'
                );
            }

            if ($this->invitationRepository->hasPendingInviteForEmail($email, $siteId)) {
                throw new \InvalidArgumentException(
                    'An invitation for this email already exists. Check your inbox or request a resend.'
                );
            }

            $request = $this->requestRepository->create([
                'site_id'    => $siteId,
                'email'      => $email,
                'name'       => $name,
                'bio'        => $bio,
                'status'     => $requiresApproval ? 'pending' : 'auto_approved',
                'created_at' => date('Y-m-d H:i:s'),
                'custom_fields' => $customFields,
            ]);

            if ($requiresApproval) {
                $this->logger->info('Contributor access request queued for review.', [
                    'email'   => $email,
                    'site_id' => $siteId,
                ]);

                return [
                    'requires_approval' => true,
                    'request'           => $request,
                    'invitation'        => null,
                ];
            }

            $invitation = $this->invitationService->create(
                email:     $email,
                invitedBy: 0,
                siteId:    $siteId,
            );

            $this->logger->info('Contributor access request auto-approved — invitation dispatched.', [
                'email'      => $email,
                'site_id'    => $siteId,
                'request_id' => $request->id,
            ]);

            return [
                'requires_approval' => false,
                'request'           => $request,
                'invitation'        => $invitation,
            ];
        });
    }

    /**
     * Admin approves a pending request and dispatches an invitation.
     *
     * @throws \InvalidArgumentException if the request is not found, not pending,
     *                                   or does not belong to the given site.
     */
    public function approve(int $requestId, int $adminId, int $siteId): \App\Models\Invitation
    {
        $request = $this->requestRepository->findForSite($requestId, $siteId);

        if (!$request) {
            throw new \InvalidArgumentException(
                "Request [{$requestId}] not found for the current site."
            );
        }

        if ($request->status !== 'pending') {
            throw new \InvalidArgumentException(
                "Request [{$requestId}] is not pending (status: {$request->status})."
            );
        }

        return $this->database->transaction(function () use ($request, $adminId): \App\Models\Invitation {
            $invitation = $this->invitationService->create(
                email:     $request->email,
                invitedBy: $adminId,
                siteId:    $request->site_id,
            );

            $this->requestRepository->update($request->id, [
                'status'      => 'approved',
                'reviewed_by' => $adminId,
                'reviewed_at' => date('Y-m-d H:i:s'),
            ]);

            return $invitation;
        });
    }

    /**
     * Admin rejects a pending request.
     *
     * @throws \InvalidArgumentException if the request is not found, not pending,
     *                                   or does not belong to the given site.
     */
    public function reject(int $requestId, int $adminId, int $siteId, ?string $reason = null): ContributorRequest
    {
        $request = $this->requestRepository->findForSite($requestId, $siteId);

        if (!$request) {
            throw new \InvalidArgumentException(
                "Request [{$requestId}] not found for the current site."
            );
        }

        if ($request->status !== 'pending') {
            throw new \InvalidArgumentException(
                "Request [{$requestId}] is not pending."
            );
        }

        $this->requestRepository->update($request->id, [
            'status'           => 'rejected',
            'reviewed_by'      => $adminId,
            'reviewed_at'      => date('Y-m-d H:i:s'),
            'rejection_reason' => $reason,
        ]);

        return $this->requestRepository->findForSite($requestId, $siteId);
    }

    /**
     * All pending requests for a site (admin queue).
     */
    public function pendingForSite(int $siteId): \App\Framework\Support\Collection
    {
        return $this->requestRepository->pendingForSite($siteId);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function normaliseEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function emailAlreadyHasSiteAccess(string $email, int $siteId): bool
    {
        $user = $this->userLifecycle->findByEmail($email);

        if (!$user) {
            return false;
        }

        return $this->authorisation->hasContributorAccess((int) $user->id, $siteId);
    }
}

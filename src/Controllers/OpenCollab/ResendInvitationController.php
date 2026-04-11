<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\InvitationRepository;
use App\Services\OpenCollab\InvitationService;

/**
 * Self-service invitation resend.
 *
 * POST /api/{site}/open-collab/invitations/resend
 * Body: { email: string }
 *
 * Always returns 200 regardless of whether an invitation exists,
 * to prevent email enumeration. The response is intentionally generic.
 *
 * Logic:
 *   1. Look up the most recent invitation for this email + site.
 *   2. If it's pending and not expired → do nothing (already live, just nudge them to check spam).
 *   3. If it's expired → create a new invitation (revoke the old one first for cleanliness).
 *   4. If none exists → do nothing (don't leak this to the caller).
 */
class ResendInvitationController extends Controller
{
    public function __construct(
        private readonly InvitationRepository $invitationRepository,
        private readonly InvitationService    $invitationService,
        private readonly Logger               $logger,
    )
    {
        parent::__construct();
    }

    /**
     * POST /api/{site}/open-collab/invitations/resend
     */
    public function resend(Request $request): JsonResponse
    {
        $email = trim($request->get('email') ?? '');

        // Always return the same response to prevent email enumeration.
        $this->processResend($email, SiteContext::getId());

        return $this->jsonResponse([
            'message' => 'If an invitation exists for that address, a fresh link has been sent.',
        ]);
    }

    private function processResend(string $email, int $siteId): void
    {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return; // Silently ignore invalid emails
        }

        // Find the most recent invitation for this email on this site
        $existing = $this->findLatestInvitation($email, $siteId);

        if (!$existing) {
            // No invitation exists — do nothing (don't reveal this)
            return;
        }

        $status = $existing->resolveStatus();

        if ($status->value === 'pending') {
            // Already live — just log that they tried. The existing link should work.
            $this->logger->info('Resend requested for already-pending invitation.', [
                'email' => $email,
                'site_id' => $siteId,
                'invitation_id' => $existing->id,
            ]);
            return;
        }

        if (in_array($status->value, ['expired', 'revoked'], true)) {
            // Expired/revoked — issue a fresh one. Use system actor (0).
            try {
                $this->invitationService->create(
                    email: $email,
                    invitedBy: 0, // self-service, no specific admin actor
                    siteId: $siteId,
                );

                $this->logger->info('Fresh invitation created via self-service resend.', [
                    'email' => $email,
                    'site_id' => $siteId,
                ]);
            } catch (\InvalidArgumentException) {
                // Already has a pending invite (race condition) — log and move on.
                $this->logger->info('Resend skipped — a pending invitation already exists.', [
                    'email' => $email,
                    'site_id' => $siteId,
                ]);
            }
        }

        // If 'used' — account already created, nothing to do.
    }

    private function findLatestInvitation(string $email, int $siteId): ?\App\Models\Invitation
    {
        return \App\Models\Invitation::where('email', $email)
            ->where('site_id', $siteId)
            ->orderByDesc('id')
            ->first();
    }
}
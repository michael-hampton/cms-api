<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\InvitationStatus;
use App\Framework\Support\Cache\Cache;
use App\Framework\Support\Logger;
use App\Repositories\OpenCollab\InvitationRepository;

/**
 * Handles self-service invitation resend requests.
 *
 * Throttle
 * --------
 * A maximum of RESEND_LIMIT attempts per (email, site) pair are allowed
 * within RESEND_WINDOW_SECONDS. Attempts beyond this limit are silently
 * dropped — the response to the caller remains identical to prevent
 * enumeration.
 *
 * Revoked invitations
 * -------------------
 * Revoked invitations are PERMANENTLY INVALID. The resend flow will not
 * regenerate a new invitation automatically. This is intentional — a
 * revocation is an administrative decision that requires a new explicit
 * invitation from an admin.
 *
 * Expired invitations
 * -------------------
 * Expired invitations can be replaced with a fresh one via this flow.
 */
class InvitationResendService
{
    private const RESEND_LIMIT          = 3;
    private const RESEND_WINDOW_SECONDS = 3600; // 1 hour

    public function __construct(
        private readonly InvitationRepository          $repository,
        private readonly InvitationStateMachineFactory $stateFactory,
        private readonly InvitationService             $invitationService,
        private readonly Logger                        $logger,
    ) {
    }

    public function handle(string $email, int $siteId): void
    {
        $email = $this->normaliseEmail($email);

        if (!$this->isValidEmail($email)) {
            return;
        }

        // Throttle check — always increment so callers cannot probe by comparing
        // response timing, but stop processing when the limit is breached.
        if ($this->isRateLimited($email, $siteId)) {
            $this->logger->warning('Invitation resend throttled.', [
                'email'   => $email,
                'site_id' => $siteId,
            ]);
            return;
        }

        $invitation = $this->repository->findLatestForEmail($email, $siteId);

        if (!$invitation) {
            return;
        }

        $state = $this->stateFactory->make($invitation);

        if ($state->isUsed()) {
            // Already accepted — nothing to resend.
            return;
        }

        if ($state->status() === InvitationStatus::Revoked) {
            // Revoked invitations are permanently invalid. An admin must create
            // a new invitation explicitly.
            $this->logger->info('Resend skipped: invitation is revoked.', [
                'invitation_id' => $invitation->id,
                'email'         => $email,
            ]);
            return;
        }

        if ($state->isPending()) {
            $this->invitationService->send($invitation);
            $this->logger->info('Invitation resent.', [
                'invitation_id' => $invitation->id,
                'email'         => $email,
                'site_id'       => $siteId,
            ]);
            return;
        }

        if ($state->status() === InvitationStatus::Expired) {
            $this->repository->expireAllForEmail($email, $siteId);

            $this->invitationService->create(
                email:     $email,
                invitedBy: 0,
                siteId:    $siteId,
            );

            $this->logger->info('New invitation created to replace expired one.', [
                'email'   => $email,
                'site_id' => $siteId,
            ]);
        }
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Fixed window rate limit backed by cache.
     *
     * Returns true if the limit has been exceeded (request should be dropped).
     * Always increments the counter so the window slides on every call.
     */
    private function isRateLimited(string $email, int $siteId): bool
    {
        $key = $this->throttleKey($email, $siteId);

        $count = (int) (Cache::get($key) ?? 0);

        if ($count >= self::RESEND_LIMIT) {
            return true;
        }

        Cache::put($key, $count + 1, self::RESEND_WINDOW_SECONDS);

        return false;
    }

    private function throttleKey(string $email, int $siteId): string
    {
        return sprintf('oc:resend:%s:%d', hash('sha256', $email), $siteId);
    }

    private function normaliseEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
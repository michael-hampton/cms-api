<?php

namespace App\Services\OpenCollab;

use App\Framework\Support\Logger;
use App\Repositories\OpenCollab\InvitationRepository;

class InvitationResendService
{
    public function __construct(
        private readonly InvitationRepository          $repository,
        private readonly InvitationStateMachineFactory $stateFactory,
        private readonly InvitationService             $invitationService,
        private readonly Logger                        $logger,
    )
    {
    }

    public function handle(string $email, int $siteId): void
    {
        if (!$this->isValidEmail($email)) {
            return;
        }

        if ($this->isRateLimited($email, $siteId)) {
            return;
        }

        $invitation = $this->repository->findLatestForEmail($email, $siteId);

        if (!$invitation) {
            return;
        }

        $state = $this->stateFactory->make($invitation);

        if ($state->isUsed()) {
            return;
        }

        if ($state->isPending()) {
            $this->invitationService->send($invitation);

//            $this->logger->info('Resent invitation', [
//                'email' => $email,
//                'site_id' => $siteId,
//            ]);

            return;
        }

        if ($state->shouldCreateNewInvite()) {
            $this->repository->expireAllForEmail($email, $siteId);

            $this->invitationService->create(
                email: $email,
                invitedBy: 0,
                siteId: $siteId
            );
        }
    }

    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function isRateLimited(string $email, int $siteId): bool
    {
        return false; // keep logic injected later if needed
    }
}
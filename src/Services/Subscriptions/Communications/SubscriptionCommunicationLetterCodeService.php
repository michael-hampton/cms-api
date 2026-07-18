<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Communications;

use App\Models\SubscriptionCommunicationLetterCode;
use App\Repositories\Subscriptions\SubscriptionCommunicationLetterCodeRepository;
use App\Repositories\Subscriptions\SubscriptionCommunicationRepository;

/**
 * Thin orchestrator for admin management of letter codes. Validation of
 * the parent communication's existence and letter-code uniqueness lives
 * here; persistence lives in the repository — same split as
 * SubscriptionCommunicationScopeService.
 */
class SubscriptionCommunicationLetterCodeService
{
    public function __construct(
        private readonly SubscriptionCommunicationRepository $communications,
        private readonly SubscriptionCommunicationLetterCodeRepository $letterCodes,
    ) {
    }

    public function all()
    {
        return $this->letterCodes->all();
    }

    public function create(int $communicationId, string $letterCode, ?string $description): SubscriptionCommunicationLetterCode
    {
        $this->findCommunicationOrFail($communicationId);
        $this->assertCodeAvailable($letterCode);

        if ($this->letterCodes->findForCommunication($communicationId) !== null) {
            throw new \RuntimeException("Communication #{$communicationId} already has a letter code.");
        }

        return $this->letterCodes->create($communicationId, $letterCode, $description);
    }

    public function update(int $id, string $letterCode, ?string $description): SubscriptionCommunicationLetterCode
    {
        $existing = $this->letterCodes->find($id);

        if ($existing === null) {
            throw new \RuntimeException("Letter code #{$id} not found.");
        }

        if ($existing->letter_code !== $letterCode) {
            $this->assertCodeAvailable($letterCode);
        }

        $updated = $this->letterCodes->update($id, $letterCode, $description);

        if ($updated === null) {
            throw new \RuntimeException("Letter code #{$id} not found.");
        }

        return $updated;
    }

    public function delete(int $id): bool
    {
        return $this->letterCodes->delete($id);
    }

    private function findCommunicationOrFail(int $communicationId): void
    {
        if (!$this->communications->find($communicationId)) {
            throw new \RuntimeException("Subscription communication #{$communicationId} not found.");
        }
    }

    private function assertCodeAvailable(string $letterCode): void
    {
        if ($this->letterCodes->findByCode($letterCode) !== null) {
            throw new \RuntimeException("Letter code [{$letterCode}] is already in use.");
        }
    }
}

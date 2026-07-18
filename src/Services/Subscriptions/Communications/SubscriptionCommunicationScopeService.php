<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Communications;

use App\Models\SubscriptionCommunicationScope;
use App\Repositories\Subscriptions\SubscriptionCommunicationRepository;
use App\Repositories\Subscriptions\SubscriptionCommunicationScopeRepository;

/**
 * Thin orchestrator for admin management of communication enable/disable
 * scoping. Validation of the parent communication's existence lives here;
 * persistence lives in the repository.
 */
class SubscriptionCommunicationScopeService
{
    public function __construct(
        private readonly SubscriptionCommunicationRepository $communications,
        private readonly SubscriptionCommunicationScopeRepository $scopes,
    ) {
    }

    public function forCommunication(int $communicationId): array
    {
        $this->findCommunicationOrFail($communicationId);

        return $this->scopes->getForCommunication($communicationId)->toArray();
    }

    public function upsert(int $communicationId, ?int $siteId, ?int $subscriptionPlanId, bool $isEnabled): SubscriptionCommunicationScope
    {
        $this->findCommunicationOrFail($communicationId);

        return $this->scopes->upsertScope($communicationId, $siteId, $subscriptionPlanId, $isEnabled);
    }

    public function delete(int $scopeId): bool
    {
        return $this->scopes->deleteScope($scopeId);
    }

    private function findCommunicationOrFail(int $communicationId): void
    {
        if (!$this->communications->find($communicationId)) {
            throw new \RuntimeException("Subscription communication #{$communicationId} not found.");
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionEditionChanged;
use App\Framework\Support\Logger;

/**
 * Audit trail for edition changes made on a subscription. Records who made
 * the change (agentId) and when, alongside the old/new edition IDs — an
 * admin-action audit trail, matching LogSubscriptionPolicySettingOverrideListener's
 * pattern for other agent-driven subscription changes.
 */
class SubscriptionEditionChangedListener
{
    public function __construct(
        private readonly Logger $logger,
    ) {
    }

    public function handle(SubscriptionEditionChanged $event): void
    {
        $this->logger->info('SubscriptionEditionChangedListener: edition changed', [
            'subscription_id' => $event->subscriptionId,
            'old_edition_id' => $event->oldEditionId,
            'new_edition_id' => $event->newEditionId,
            'agent_id' => $event->agentId,
            'timestamp' => $event->timestamp,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionPlanChanged;
use App\Framework\Support\Logger;

/**
 * Audit trail for plan changes made on a subscription. Records who made
 * the change (agentId) and when, alongside the old/new plan IDs — an
 * admin-action audit trail, matching LogSubscriptionPolicySettingOverrideListener's
 * pattern for other agent-driven subscription changes.
 */
class SubscriptionPlanChangedListener
{
    public function __construct(
        private readonly Logger $logger,
    ) {
    }

    public function handle(SubscriptionPlanChanged $event): void
    {
        $this->logger->info('SubscriptionPlanChangedListener: plan changed', [
            'subscription_id' => $event->subscriptionId,
            'old_plan_id' => $event->oldPlanId,
            'new_plan_id' => $event->newPlanId,
            'agent_id' => $event->agentId,
            'timestamp' => $event->timestamp,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions;

use App\Models\SubscriptionCommunicationScope;

/**
 * Resolves and manages enable/disable scoping for a subscription_communication
 * at system, site, or plan level.
 *
 * Resolution order (most specific wins):
 *   (site + plan) > site only > plan only > system default (both null)
 */
class SubscriptionCommunicationScopeRepository
{
    public function isEnabled(int $communicationId, ?int $siteId, ?int $subscriptionPlanId): bool
    {
        $candidates = [
            ['site_id' => $siteId, 'subscription_plan_id' => $subscriptionPlanId],
            ['site_id' => $siteId, 'subscription_plan_id' => null],
            ['site_id' => null, 'subscription_plan_id' => $subscriptionPlanId],
            ['site_id' => null, 'subscription_plan_id' => null],
        ];

        // De-dupe candidates (e.g. when siteId/subscriptionPlanId are already null).
        $seen = [];

        foreach ($candidates as $candidate) {
            $key = $candidate['site_id'] . ':' . $candidate['subscription_plan_id'];

            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $row = $this->findRow($communicationId, $candidate['site_id'], $candidate['subscription_plan_id']);

            if ($row !== null) {
                return $row->is_enabled;
            }
        }

        // No scope rows configured at all for this communication — default
        // open, matching subscription_communications.is_active's own default.
        return true;
    }

    public function upsertScope(
        int $communicationId,
        ?int $siteId,
        ?int $subscriptionPlanId,
        bool $isEnabled,
    ): SubscriptionCommunicationScope {
        $existing = $this->findRow($communicationId, $siteId, $subscriptionPlanId);

        if ($existing !== null) {
            $existing->update(['is_enabled' => $isEnabled]);
            return $existing;
        }

        return SubscriptionCommunicationScope::create([
            'subscription_communication_id' => $communicationId,
            'site_id' => $siteId,
            'subscription_plan_id' => $subscriptionPlanId,
            'is_enabled' => $isEnabled,
        ]);
    }

    public function getForCommunication(int $communicationId)
    {
        return SubscriptionCommunicationScope::where('subscription_communication_id', $communicationId)
            ->orderByDesc('id')
            ->get();
    }

    public function deleteScope(int $scopeId): bool
    {
        $scope = SubscriptionCommunicationScope::find($scopeId);

        if ($scope === null) {
            return false;
        }

        return (bool) $scope->delete();
    }

    private function findRow(int $communicationId, ?int $siteId, ?int $subscriptionPlanId): ?SubscriptionCommunicationScope
    {
        $query = SubscriptionCommunicationScope::where('subscription_communication_id', $communicationId);

        $siteId === null ? $query->whereNull('site_id') : $query->where('site_id', $siteId);
        $subscriptionPlanId === null
            ? $query->whereNull('subscription_plan_id')
            : $query->where('subscription_plan_id', $subscriptionPlanId);

        return $query->first();
    }
}

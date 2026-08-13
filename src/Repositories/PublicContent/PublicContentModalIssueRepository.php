<?php

namespace App\Repositories\PublicContent;

use App\Enums\Subscriptions\IssueDeliveryStatus;
use App\Models\IssueDelivery;

/**
 * Public-content read model for subscription modal issue availability.
 */
class PublicContentModalIssueRepository
{
    /**
     * @param list<int> $planIds
     * @return array<int, IssueDelivery>
     */
    public function nextIssuesByPlanIds(array $planIds): array
    {
        $planIds = array_values(array_unique(array_filter(
            array_map(static fn(mixed $id): int => (int) $id, $planIds),
            static fn(int $id): bool => $id > 0,
        )));

        if ($planIds === []) {
            return [];
        }

        $cutoff = now_datetime()->modify('-7 days')->format('Y-m-d H:i:s');

        $issues = IssueDelivery::query()
            ->whereIn('subscription_plan_id', $planIds)
            ->where('status', IssueDeliveryStatus::ACTIVE->value)
            ->where(static function ($query) use ($cutoff): void {
                $query->where('on_sale_date', '>=', $cutoff)
                    ->orWhereNull('on_sale_date');
            })
            ->orderBy('on_sale_date', 'asc')
            ->get();

        $firstByPlan = [];
        foreach ($issues as $issue) {
            if (!$issue instanceof IssueDelivery) {
                continue;
            }

            $planId = (int) $issue->subscription_plan_id;
            if (!isset($firstByPlan[$planId])) {
                $firstByPlan[$planId] = $issue;
            }
        }

        return $firstByPlan;
    }
}

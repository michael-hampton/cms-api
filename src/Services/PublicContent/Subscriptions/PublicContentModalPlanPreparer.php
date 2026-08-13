<?php

namespace App\Services\PublicContent\Subscriptions;

use App\Framework\Support\Collection;
use App\Models\SubscriptionPlan;
use App\Repositories\PublicContent\PublicContentModalIssueRepository;

/**
 * Prefetches next issues for modal plans and replaces plan models with
 * public-content view models before compose renders the modal widget.
 */
final class PublicContentModalPlanPreparer
{
    public function __construct(
        private readonly PublicContentModalPlanPricing $pricing,
        private readonly PublicContentModalIssueRepository $issues,
    ) {
    }

    /**
     * @param array{show_modal: bool, plans: mixed, member: mixed} $modalData
     * @return array{show_modal: bool, plans: Collection, member: mixed}
     */
    public function prepare(array $modalData): array
    {
        $plans = $this->planList($modalData['plans'] ?? null);
        $planIds = [];
        foreach ($plans as $plan) {
            $planIds[] = (int) $plan->id;
        }
        $nextIssues = $this->issues->nextIssuesByPlanIds($planIds);

        $prepared = [];
        foreach ($plans as $plan) {
            $issue = $nextIssues[(int) $plan->id] ?? null;
            $prepared[] = new PublicContentModalPlanViewModel(
                $plan,
                $this->pricing->lowestEffectivePrice($plan, $issue),
                $this->pricing->availableDeliveryOptions($plan, $issue),
            );
        }

        $modalData['plans'] = new Collection($prepared);

        return $modalData;
    }

    /**
     * @return list<SubscriptionPlan>
     */
    private function planList(mixed $plans): array
    {
        if ($plans instanceof Collection) {
            $plans = $plans->all();
        }

        if (!is_array($plans)) {
            return [];
        }

        $list = [];
        foreach ($plans as $plan) {
            if ($plan instanceof SubscriptionPlan) {
                $list[] = $plan;
            }
        }

        return $list;
    }
}

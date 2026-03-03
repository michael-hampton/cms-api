<?php

namespace App\Actions\SubscriptionPlan;

use App\Repositories\Subscriptions\SubscriptionPlanRepository;

class BulkTogglePlanActive
{
    public function __construct(
        private readonly SubscriptionPlanRepository $planRepository
    )
    {
    }

    public function handle(array $planIds, bool $active): array
    {
        $updated = [];
        $failed = [];

        foreach ($planIds as $planId) {
            try {
                $plan = $this->planRepository->find($planId);

                if (!$plan) {
                    $failed[] = ['id' => $planId, 'reason' => 'Plan not found'];
                    continue;
                }

                $result = $this->planRepository->update($planId, ['is_active' => $active]);

                if ($result) {
                    $updated[] = $planId;
                } else {
                    $failed[] = ['id' => $planId, 'reason' => 'Update failed'];
                }
            } catch (\Exception $e) {
                $failed[] = ['id' => $planId, 'reason' => $e->getMessage()];
            }
        }

        return [
            'updated' => $updated,
            'failed' => $failed,
            'total' => count($planIds),
        ];
    }
}
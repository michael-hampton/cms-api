<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\MemberInsights\Segmentation\PlanSegmentRecalculationService;

class RecalculateSegmentsCommand extends Command
{
    use ReportsCommandResult;

    protected $signature = 'segments:recalculate {--plan= : Recalculate only the specified plan ID}';
    public $description = 'Recalculate subscription segment assignments for one or all plans.';

    const SUCCESS = 1;
    const FAILURE = 0;

    public function __construct(
        private readonly PlanSegmentRecalculationService $recalculationService,
        private readonly SubscriptionPlanRepository                  $planRepository,
    ) {

    }

    public function handle(): int
    {
        $planId = $this->option('plan') ? (int) $this->option('plan') : null;

        if ($planId !== null) {
            return $this->recalculateSinglePlan($planId);
        }

        return $this->recalculateAllPlans();
    }

    private function recalculateSinglePlan(int $planId): int
    {
        try {
            $count = $this->recalculationService->recalculatePlan($planId);
            $this->info("Recalculated {$count} subscriptions for plan #{$planId}.");
            return self::SUCCESS;
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function recalculateAllPlans(): int
    {
        $result = $this->createResult('content:calculate-trending');

        $plans = $this->planRepository->getActivePlans();
        $total = 0;

        foreach ($plans as $plan) {
            $count = $this->recalculationService->recalculatePlan($plan->id);
            $result->addMessage("Plan #{$plan->id}: {$count} subscriptions processed.");
            $total += $count;
        }

        $this->info("Total: {$total} subscriptions recalculated across " . count($plans) . " plans.");
        return self::SUCCESS;
    }
}
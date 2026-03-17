<?php

declare(strict_types=1);

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Repositories\Subscriptions\SubscriptionPricingChangeRepository;
use App\Services\Subscriptions\SubscriptionPricingChangeService;

/**
 * Applies subscription price changes whose effective date has passed.
 *
 * Schedule in Kernel: $schedule->command(ApplyDuePricingChangesCommand::class)->daily();
 */
class ApplyDuePricingChangesCommand extends Command
{
    use ReportsCommandResult;

    const SUCCESS = 1;
    const FAILURE = 0;

    protected $signature = 'subscriptions:apply-price-changes';
    public $description = 'Applies subscription pricing changes that have passed their effective date.';

    public function __construct(
        private readonly SubscriptionPricingChangeRepository $repository,
        private readonly SubscriptionPricingChangeService    $service,
    )
    {
    }

    public function handle(): int
    {
        $result = $this->createResult('subscriptions:apply-price-changes');
        $changes = $this->repository->findDueToApply();

        if (empty($changes)) {
            $this->info('No pricing changes due to apply.');
            return self::SUCCESS;
        }

        foreach ($changes as $change) {
            try {
                $this->service->apply($change);

                $result->incrementSucceeded();
                $result->addMessage(
                    "Applied change #{$change->id} (plan #{$change->plan_id}: "
                    . "{$change->currency} {$change->old_price} → {$change->new_price})"
                );

            } catch (\Throwable $e) {
                $this->reportFailure(
                    result: $result,
                    message: "Failed to apply pricing change #{$change->id}: {$e->getMessage()}",
                    context: ['pricing_change_id' => $change->id, 'plan_id' => $change->plan_id],
                    throwable: $e,
                );
            }
        }

        $this->reportResult($result);

        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}
<?php

declare(strict_types=1);

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Support\Logger;
use App\Repositories\Subscriptions\SubscriptionPricingChangeRepository;
use App\Services\Subscriptions\SubscriptionPricingChangeService;

/**
 * Applies subscription price changes whose effective date has passed.
 *
 * Schedule in Kernel: $schedule->command(ApplyDuePricingChangesCommand::class)->daily();
 */
class ApplyDuePricingChangesCommand extends Command
{
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
        $changes = $this->repository->findDueToApply();

        if (empty($changes)) {
            $this->info('No pricing changes due to apply.');
            return self::SUCCESS;
        }

        $applied = 0;
        $failed = 0;

        foreach ($changes as $change) {
            try {
                $this->service->apply($change);
                $applied++;
                $this->info("Applied pricing change #{$change->id} (plan #{$change->plan_id}: {$change->currency} {$change->old_price} → {$change->new_price})");
            } catch (\Throwable $e) {
                $failed++;
                Logger::error('ApplyDuePricingChangesCommand: failed to apply pricing change', [
                    'pricing_change_id' => $change->id,
                    'plan_id' => $change->plan_id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed to apply pricing change #{$change->id}: {$e->getMessage()}");
            }
        }

        $this->info("Done. Applied: {$applied}, Failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
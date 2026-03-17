<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Models\SubscriptionPremiumAccess;

class ExpirePremiumAccessCommand extends Command
{
    use ReportsCommandResult;

    const SUCCESS = 1;
    const FAILURE = 0;

    protected $signature = 'subscriptions:expire-premium-access';
    public $description = 'Expires premium access grants that have passed their expiration date.';

    public function handle(): int
    {
        $result = $this->createResult('subscriptions:expire-premium-access');

        $expiredAccess = SubscriptionPremiumAccess::where('is_active', true)
            ->where('expires_at', '<', now_datetime())
            ->get();

        foreach ($expiredAccess as $access) {
            try {
                $access->update(['is_active' => false]);

                $result->incrementSucceeded();
                $result->addMessage("Expired {$access->premium_type}:{$access->premium_identifier} for subscription #{$access->subscription_id}");
            } catch (\Throwable $e) {
                $this->reportFailure(
                    result: $result,
                    message: "Failed to expire access #{$access->id}: {$e->getMessage()}",
                    context: ['access_id' => $access->id],
                    throwable: $e
                );
            }
        }

        $this->reportResult($result);
        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}
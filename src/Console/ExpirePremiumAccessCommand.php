<?php

namespace App\Console;

use App\Models\SubscriptionPremiumAccess;

class ExpirePremiumAccessCommand
{
    public function execute(): void
    {
        echo "Checking for expired premium access...\n";

        $expiredAccess = SubscriptionPremiumAccess::where('is_active', true)
            ->where('expires_at', '<', now_datetime())
            ->get();

        foreach ($expiredAccess as $access) {
            $access->update(['is_active' => false]);

            echo "Expired {$access->premium_type}:{$access->premium_identifier} for subscription {$access->subscription_id}\n";

            \App\Framework\Support\Logger::info('Premium access expired', [
                'subscription_id' => $access->subscription_id,
                'premium_type' => $access->premium_type,
                'premium_identifier' => $access->premium_identifier
            ]);
        }

        echo "Expired {$expiredAccess->count()} premium access grants.\n";
    }
}
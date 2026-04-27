<?php

namespace App\Listeners\OpenCollab;

use App\Events\OpenCollab\PayoutProcessedEvent;
use App\Services\BaseUserNotificationListener;

class SendPayoutProcessedNotification extends BaseUserNotificationListener
{
    public function handle(PayoutProcessedEvent $event): void
    {
        $this->notify(
            $event->userId,
            'payout_processed',
            [
                'amount' => $event->payout->amount,
            ]
        );
    }
}
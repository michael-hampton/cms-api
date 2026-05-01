<?php

namespace App\Listeners\OpenCollab;

use App\Events\OpenCollab\PayoutFailedEvent;
use App\Services\BaseUserNotificationListener;

class SendPayoutFailedNotification extends BaseUserNotificationListener
{
    public function handle(PayoutFailedEvent $event): void
    {
        $this->notify(
            $event->userId,
            'payout_failed',
            [
                'reason' => $event->reason,
            ],
            'contributor.payout_failed'
        );
    }
}
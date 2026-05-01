<?php

namespace App\Listeners\OpenCollab;

use App\Events\OpenCollab\DisputeRaisedEvent;
use App\Services\BaseUserNotificationListener;

class SendDisputeRaisedNotification extends BaseUserNotificationListener
{
    public function handle(DisputeRaisedEvent $event): void
    {
        $this->notify(
            $event->userId,
            'dispute_raised',
            [
                'dispute_id' => $event->disputeId,
            ],
            'contributor.dispute_raised'
        );
    }
}
<?php

namespace App\Listeners\OpenCollab;

use App\Events\OpenCollab\DisputeResolvedEvent;
use App\Services\BaseUserNotificationListener;

class SendDisputeResolvedNotification extends BaseUserNotificationListener
{
    public function handle(DisputeResolvedEvent $event): void
    {
        $this->notify(
            $event->userId,
            'dispute_resolved',
            [
                'dispute_id' => $event->disputeId,
                'outcome' => $event->outcome,
            ],
            'contributor.dispute_resolved'
        );
    }
}
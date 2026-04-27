<?php

namespace App\Listeners\OpenCollab;

use App\Events\OpenCollab\ContractPublishedEvent;
use App\Services\BaseUserNotificationListener;

class SendContractPublishedNotification extends BaseUserNotificationListener
{
    public function handle(ContractPublishedEvent $event): void
    {
        $this->notify(
            $event->userId,
            'contract_published',
            [
                'contract_id' => $event->contract->id,
            ]
        );
    }
}
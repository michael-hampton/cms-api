<?php

namespace App\Listeners\OpenCollab;

use App\Events\OpenCollab\ContractPublishedEvent;
use App\Services\BaseUserNotificationListener;

class SendContractPublishedNotification extends BaseUserNotificationListener
{
    public function handle(ContractPublishedEvent $event): void
    {
        $this->notifyAllContributors(
            siteId: $event->siteId,
            type: 'contract_published',
            title: 'New contributor agreement',
            body: "A new contract (v{$event->contract->version}) requires your signature.",
            data: ['contract_id' => $event->contract->id],
        );
    }
}
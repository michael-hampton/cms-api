<?php

namespace App\Listeners\OpenCollab;

use App\Events\OpenCollab\GuidelinesVersionBumpedEvent;
use App\Services\BaseUserNotificationListener;

class SendGuidelinesUpdatedNotification extends BaseUserNotificationListener
{
    public function handle(GuidelinesVersionBumpedEvent $event): void
    {
        $this->notifyAllContributors(
            siteId: $event->siteId,
            type: 'guidelines_updated',
            title: 'Guidelines updated',
            body: "Brand guidelines (v{$event->newVersion}) require your acknowledgement.",
            data: ['version' => $event->newVersion],
            consentType: 'contributor.guidelines_updated'
        );
    }
}
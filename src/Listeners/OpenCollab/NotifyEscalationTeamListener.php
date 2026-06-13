<?php

namespace App\Listeners\OpenCollab;

use App\Events\OpenCollab\ModerationEscalationCreatedEvent;
use App\Framework\Notifications\NotificationDispatcher;
use App\Services\OpenCollab\Notifications\EscalationCreatedNotification; // ASSUMED, create analogous to ViolationRecordedNotification

class NotifyEscalationTeamListener
{
    public function __construct(
        private readonly NotificationDispatcher $notificationDispatcher,
    ) {
    }

    public function handle(ModerationEscalationCreatedEvent $event): void
    {
        // ASSUMED: NotificationDispatcher can target a team/role, not just a user.
        // If not, resolve the team's users here via a repository and dispatch per-user.
        $this->notificationDispatcher->dispatch(
            new EscalationCreatedNotification($event->escalation)
        );
    }
}
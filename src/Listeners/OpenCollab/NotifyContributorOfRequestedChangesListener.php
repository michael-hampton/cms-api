<?php

namespace App\Listeners\OpenCollab;

use App\Events\OpenCollab\ChangesRequestedEvent;
use App\Framework\Notifications\NotificationDispatcher;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Services\OpenCollab\Notifications\ChangesRequestedNotification; // ASSUMED: create analogous to ArticleRejectedNotification

class NotifyContributorOfRequestedChangesListener
{
    public function __construct(
        private readonly NotificationDispatcher $notificationDispatcher,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function handle(ChangesRequestedEvent $event): void
    {
        $contributor = $this->userRepository->find((int) $event->page->contributor_id);

        if ($contributor === null) {
            return;
        }

        $this->notificationDispatcher->dispatch(
            new ChangesRequestedNotification($event->page, $event->notes, $contributor)
        );
    }
}
<?php

namespace App\Listeners\OpenCollab;

use App\Events\OpenCollab\ArticleRejectedEvent;
use App\Services\BaseUserNotificationListener;

class SendArticleRejectedNotification extends BaseUserNotificationListener
{
    public function handle(ArticleRejectedEvent $event): void
    {
        $this->notify(
            $event->userId,
            'article_rejected',
            [
                'article_id' => $event->page->id,
                'reason' => $event->reason,
            ],
            'contributor.article_rejected'
        );
    }
}
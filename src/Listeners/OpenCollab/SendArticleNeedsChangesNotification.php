<?php

namespace App\Listeners\OpenCollab;

use App\Events\OpenCollab\ArticleNeedsChangesEvent;
use App\Services\BaseUserNotificationListener;

class SendArticleNeedsChangesNotification extends BaseUserNotificationListener
{
    public function handle(ArticleNeedsChangesEvent $event): void
    {
        $this->notify(
            $event->userId,
            'article_needs_changes',
            [
                'article_id' => $event->articleId,
                'feedback' => $event->feedback,
            ]
        );
    }
}
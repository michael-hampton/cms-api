<?php

namespace App\Listeners\OpenCollab;

use App\Events\OpenCollab\ArticleApprovedEvent;
use App\Services\BaseUserNotificationListener;

class SendArticleApprovedNotification extends BaseUserNotificationListener
{
    public function handle(ArticleApprovedEvent $event): void
    {
        $this->notify(
            $event->userId,
            'article_approved',
            [
                'article_id' => $event->page->id,
                'title' => $event->page->title,
            ],
            'contributor.article_approved'
        );
    }
}
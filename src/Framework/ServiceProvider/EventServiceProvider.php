<?php

namespace App\Framework\ServiceProvider;

use App\Events\Cms\ContentApproved;
use App\Events\Cms\ContentHeld;
use App\Events\Cms\ContentRejected;
use App\Events\Cms\ContentSubmittedForApproval;
use App\Events\Notifications\EmailNotificationSent;
use App\Events\OpenCollab\RiskMarkerStatusChangedEvent;
use App\Framework\Container;
use App\Framework\Events\EventDispatcher;
use App\Listeners\Cms\SendContentWorkflowNotification;
use App\Listeners\Notifications\RecordEmailCommunicationLog;
use App\Listeners\OpenCollab\RecalculateQueuePriorityListener;

class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $dispatcher = Container::getInstance()->resolve(EventDispatcher::class);

        $listener = [SendContentWorkflowNotification::class, 'handle'];

        $dispatcher->listen(ContentSubmittedForApproval::class, $listener);
        $dispatcher->listen(ContentApproved::class, $listener);
        $dispatcher->listen(ContentRejected::class, $listener);
        $dispatcher->listen(ContentHeld::class, $listener);

        $dispatcher->listen(
            RiskMarkerStatusChangedEvent::class,
            [RecalculateQueuePriorityListener::class, 'handle']
        );

        $dispatcher->listen(
            EmailNotificationSent::class,
            [RecordEmailCommunicationLog::class, 'handle']
        );
    }
}

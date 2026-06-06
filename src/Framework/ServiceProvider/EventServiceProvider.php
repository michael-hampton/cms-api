<?php

namespace App\Framework\ServiceProvider;

use App\Events\Cms\ContentApproved;
use App\Events\Cms\ContentHeld;
use App\Events\Cms\ContentRejected;
use App\Events\Cms\ContentSubmittedForApproval;
use App\Framework\Container;
use App\Framework\Events\EventDispatcher;
use App\Listeners\Cms\SendContentWorkflowNotification;

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
    }
}

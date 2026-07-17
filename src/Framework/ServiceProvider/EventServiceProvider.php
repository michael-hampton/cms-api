<?php

namespace App\Framework\ServiceProvider;

use App\Events\Billing\DefaultPaymentMethodChanged;
use App\Events\Billing\PaymentMethodAdded;
use App\Events\Billing\PaymentMethodRemoved;
use App\Events\Billing\SubscriptionPaymentMethodChanged;
use App\Events\Cms\ContentApproved;
use App\Events\Cms\ContentHeld;
use App\Events\Cms\ContentRejected;
use App\Events\Cms\ContentSubmittedForApproval;
use App\Events\Notifications\EmailNotificationSent;
use App\Events\OpenCollab\RiskMarkerStatusChangedEvent;
use App\Events\Subscriptions\PaymentFailed;
use App\Events\Subscriptions\PaymentRefunded;
use App\Events\Subscriptions\PaymentSucceeded;
use App\Events\Subscriptions\SubscriptionCancelled;
use App\Events\Subscriptions\SubscriptionCreated;
use App\Events\Subscriptions\SubscriptionPaused;
use App\Events\Subscriptions\SubscriptionPolicySettingOverridden;
use App\Events\Subscriptions\SubscriptionPolicySettingOverrideCleared;
use App\Events\Subscriptions\SubscriptionReactivated;
use App\Events\Subscriptions\SubscriptionResumed;
use App\Framework\Container;
use App\Framework\Events\EventDispatcher;
use App\Listeners\Billing\LogPaymentMethodAnalyticsListener;
use App\Listeners\Cms\SendContentWorkflowNotification;
use App\Listeners\Notifications\RecordEmailCommunicationLog;
use App\Listeners\OpenCollab\RecalculateQueuePriorityListener;
use App\Listeners\Subscriptions\LogSubscriptionPolicySettingOverrideListener;
use App\Listeners\Subscriptions\SendSubscriptionLifecycleCommunicationListener;

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

        $dispatcher->listen(
            SubscriptionCreated::class,
            [SendSubscriptionLifecycleCommunicationListener::class, 'handleSubscriptionCreated']
        );
        $dispatcher->listen(
            SubscriptionCancelled::class,
            [SendSubscriptionLifecycleCommunicationListener::class, 'handleSubscriptionCancelled']
        );
        $dispatcher->listen(
            SubscriptionReactivated::class,
            [SendSubscriptionLifecycleCommunicationListener::class, 'handleSubscriptionReactivated']
        );
        $dispatcher->listen(
            SubscriptionPaused::class,
            [SendSubscriptionLifecycleCommunicationListener::class, 'handleSubscriptionPaused']
        );
        $dispatcher->listen(
            SubscriptionResumed::class,
            [SendSubscriptionLifecycleCommunicationListener::class, 'handleSubscriptionResumed']
        );
        $dispatcher->listen(
            PaymentSucceeded::class,
            [SendSubscriptionLifecycleCommunicationListener::class, 'handlePaymentSucceeded']
        );
        $dispatcher->listen(
            PaymentFailed::class,
            [SendSubscriptionLifecycleCommunicationListener::class, 'handlePaymentFailed']
        );
        $dispatcher->listen(
            PaymentRefunded::class,
            [SendSubscriptionLifecycleCommunicationListener::class, 'handlePaymentRefunded']
        );

        $dispatcher->listen(
            PaymentMethodAdded::class,
            [LogPaymentMethodAnalyticsListener::class, 'handleAdded']
        );
        $dispatcher->listen(
            PaymentMethodRemoved::class,
            [LogPaymentMethodAnalyticsListener::class, 'handleRemoved']
        );
        $dispatcher->listen(
            DefaultPaymentMethodChanged::class,
            [LogPaymentMethodAnalyticsListener::class, 'handleDefaultChanged']
        );
        $dispatcher->listen(
            SubscriptionPaymentMethodChanged::class,
            [LogPaymentMethodAnalyticsListener::class, 'handleSubscriptionPaymentMethodChanged']
        );

        $dispatcher->listen(
            SubscriptionPolicySettingOverridden::class,
            [LogSubscriptionPolicySettingOverrideListener::class, 'handleOverridden']
        );
        $dispatcher->listen(
            SubscriptionPolicySettingOverrideCleared::class,
            [LogSubscriptionPolicySettingOverrideListener::class, 'handleCleared']
        );
    }
}
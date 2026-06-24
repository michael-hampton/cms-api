<?php

namespace App\Framework\Notifications;

use App\Framework\Events\EventDispatcher;
use App\Framework\Mail\MailManager;
use App\Framework\Notifications\Channels\AdminEmailChannel;
use App\Framework\Notifications\Channels\EmailChannel;
use App\Framework\Notifications\Channels\LogChannel;
use App\Framework\Support\Config;
use App\Framework\Support\Logger;
use App\Services\OpenCollab\UserConsentService;

/**
 * Builds a fully-wired NotificationDispatcher for the DI container.
 *
 * Container binding example:
 *
 *   $container->bind(NotificationDispatcher::class, fn($c) =>
 *       NotificationDispatcherFactory::make(
 *           $c->get(MailManager::class),
 *           $c->get(Logger::class),
 *           $c->get(UserConsentService::class),
 *           $c->get(EventDispatcher::class),
 *       )
 *   );
 *
 * To add a new channel (Slack, push, SMS, etc.):
 *   1. Create a class implementing ChannelInterface.
 *   2. Add it to the $channels array in make(). That is the only change needed.
 */
final class NotificationDispatcherFactory
{
    public static function make(
        MailManager        $mailManager,
        Logger             $logger,
        UserConsentService $consentService,
        EventDispatcher    $events,
    ): NotificationDispatcher
    {
        $adminEmail = Config::get('opencollab.admin_notification_email', 'admin@example.com');

        $channels = [
            new LogChannel($logger),
            new EmailChannel($mailManager, $logger, $consentService, $events),
            new AdminEmailChannel($mailManager, $logger, $adminEmail),
        ];

        return new NotificationDispatcher($channels, $logger);
    }
}

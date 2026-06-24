<?php

namespace App\Framework\Notifications\Channels;

use App\Events\Notifications\EmailNotificationSent;
use App\Framework\Events\EventDispatcher;
use App\Framework\Mail\MailManager;
use App\Framework\Notifications\AdminNotification;
use App\Framework\Notifications\ChannelInterface;
use App\Framework\Notifications\ConsentAwareNotification;
use App\Framework\Notifications\EmailableNotification;
use App\Framework\Notifications\NotificationInterface;
use App\Framework\Support\Logger;
use App\Listeners\Notifications\RecordEmailCommunicationLog;
use App\Services\OpenCollab\UserConsentService;

/**
 * Delivers notifications to individual recipients via email.
 *
 * Supports any notification that:
 *   (a) implements EmailableNotification, AND
 *   (b) returns a non-empty recipientEmail(), AND
 *   (c) does NOT implement AdminNotification
 *       (admin-targeted notifications go through AdminEmailChannel)
 *
 * This channel has zero knowledge of domain notification classes or
 * mailable implementations — that knowledge lives in the notification itself
 * via toMailable().
 */
final class EmailChannel implements ChannelInterface
{
    public function __construct(
        private readonly MailManager        $mailManager,
        private readonly Logger             $logger,
        private readonly UserConsentService $consentService,
        private readonly EventDispatcher    $events,
    )
    {
    }

    public function supports(NotificationInterface $notification): bool
    {
        return $notification instanceof EmailableNotification
            && !($notification instanceof AdminNotification)
            && !empty($notification->recipientEmail());
    }

    public function send(NotificationInterface $notification): bool
    {
        /** @var EmailableNotification $notification */
        $email = $notification->recipientEmail();

        if (
            $notification instanceof ConsentAwareNotification &&
            $notification->recipientUserId() !== null &&
            !$this->consentService->hasConsent($notification->recipientUserId(), $notification->consentType(), 'email')
        ) {
            return false;
        }

        try {
            $mailable = $notification->toMailable();

            $sent = $this->mailManager
                ->to($email)
                ->send($mailable);

            if ($sent && $notification->recipientUserId() !== null) {
                $this->recordSentEmail(new EmailNotificationSent(
                    recipientUserId: $notification->recipientUserId(),
                    recipientEmail: $email,
                    subject: $notification->subject(),
                    notificationClass: get_class($notification),
                    mailableClass: get_class($mailable),
                ));
            }

            return $sent;
        } catch (\Throwable $e) {
            $this->logger->error('EmailChannel: failed to send.', [
                'notification' => get_class($notification),
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function recordSentEmail(EmailNotificationSent $event): void
    {
        if ($this->events->hasListeners(EmailNotificationSent::class)) {
            $this->events->dispatch($event);
            return;
        }

        // ApiApplication replaces the EventDispatcher instance after configured
        // providers have registered listeners. Until that is consolidated, keep
        // the event/listener behaviour reliable by invoking the same listener
        // through the container when no listener is registered on this dispatcher.
        app(RecordEmailCommunicationLog::class)->handle($event);
    }
}

<?php

namespace App\Framework\Notifications\Channels;

use App\Framework\Mail\MailManager;
use App\Framework\Notifications\AdminNotification;
use App\Framework\Notifications\ChannelInterface;
use App\Framework\Notifications\EmailableNotification;
use App\Framework\Notifications\NotificationInterface;
use App\Framework\Support\Logger;

/**
 * Delivers admin-targeted notifications to a configured site email address.
 *
 * Supports notifications that implement BOTH:
 *   - AdminNotification   (marker: targets admin team, not a specific user)
 *   - EmailableNotification (carries its own mailable via toMailable())
 *
 * The recipient address is resolved from site configuration, not from
 * the notification itself — notifications implementing AdminNotification
 * intentionally return null from recipientEmail().
 */
final class AdminEmailChannel implements ChannelInterface
{
    public function __construct(
        private readonly MailManager $mailManager,
        private readonly Logger      $logger,
        private readonly string      $adminEmail,
    )
    {
    }

    public function supports(NotificationInterface $notification): bool
    {
        return $notification instanceof AdminNotification
            && $notification instanceof EmailableNotification;
    }

    public function send(NotificationInterface $notification): bool
    {
        /** @var EmailableNotification $notification */
        try {
            return $this->mailManager
                ->to($this->adminEmail)
                ->send($notification->toMailable());
        } catch (\Throwable $e) {
            $this->logger->error('AdminEmailChannel: failed to send.', [
                'notification' => get_class($notification),
                'admin_email' => $this->adminEmail,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
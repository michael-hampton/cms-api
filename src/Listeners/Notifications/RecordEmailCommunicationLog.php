<?php

namespace App\Listeners\Notifications;

use App\Events\Notifications\EmailNotificationSent;
use App\Framework\Support\Logger;
use App\Repositories\Members\CommunicationLogRepository;

final class RecordEmailCommunicationLog
{
    public function __construct(
        private readonly CommunicationLogRepository $communicationLogRepository,
        private readonly Logger                     $logger,
    ) {
    }

    public function handle(EmailNotificationSent $event): void
    {
        try {
            $this->communicationLogRepository->record([
                'member_id'     => $event->recipientUserId,
                'type'          => 'transactional',
                'channel'       => 'email',
                'subject'       => $event->subject,
                'status'        => 'sent',
                'template_name' => $event->mailableClass,
                'campaign_name' => $event->notificationClass,
                'sent_at'       => now_datetime(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('RecordEmailCommunicationLog: failed to write communication log', [
                'recipient_user_id' => $event->recipientUserId,
                'recipient_email' => $event->recipientEmail,
                'notification_class' => $event->notificationClass,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

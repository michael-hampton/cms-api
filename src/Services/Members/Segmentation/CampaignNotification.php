<?php

namespace App\Services\Members\Segmentation;

use App\Framework\Mail\Mailable;
use App\Framework\Notifications\AbstractNotification;
use App\Framework\Notifications\EmailableNotification;

/**
 * Generic notification wrapper for all campaign mailings.
 *
 * The framework's EmailChannel requires a NotificationInterface that
 * also implements EmailableNotification. This class is that bridge —
 * it holds a pre-built Mailable and surfaces it through toMailable()
 * so EmailChannel can hand it to MailManager without knowing which
 * campaign or mailable class is involved.
 *
 * Usage:
 *   $notification = new CampaignNotification(
 *       mailable: new WeMissYouMail($member, $campaign),
 *       recipientEmail: $member->email,
 *       recipientUserId: $member->id,
 *   );
 *   $dispatcher->dispatch($notification);
 */
final class CampaignNotification extends AbstractNotification implements EmailableNotification
{
    public function __construct(
        private readonly Mailable $mailable,
        private readonly string   $recipientEmailAddress,
        private readonly int      $recipientUserIdValue,
    )
    {
        parent::__construct(
            userId: $recipientUserIdValue,
            email: $recipientEmailAddress,
        );
    }

    public function subject(): string
    {
        return $this->mailable->subject;
    }

    public function recipientEmail(): ?string
    {
        return $this->recipientEmailAddress;
    }

    public function recipientUserId(): ?int
    {
        return $this->recipientUserIdValue;
    }

    public function toMailable(): Mailable
    {
        return $this->mailable;
    }
}
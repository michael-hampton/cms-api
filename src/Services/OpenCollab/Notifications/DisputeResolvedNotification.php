<?php

namespace App\Services\OpenCollab\Notifications;

use App\Framework\Mail\Mailable;
use App\Framework\Notifications\AbstractNotification;
use App\Framework\Notifications\EmailableNotification;
use App\Mail\OpenCollab\DisputeResolvedMail;
use App\Models\EarningsDispute;
use App\Models\User;

final class DisputeResolvedNotification extends AbstractNotification
    implements EmailableNotification
{
    public function __construct(
        public readonly EarningsDispute $dispute,
        public readonly User            $contributor,
        public readonly bool            $wasApproved,
        public readonly ?string         $adminNotes = null,
    )
    {
        parent::__construct(userId: $contributor->id, email: $contributor->email);
    }

    public function subject(): string
    {
        return $this->wasApproved
            ? "Your earnings dispute has been resolved in your favour"
            : "Update on your earnings dispute";
    }

    public function toMailable(): Mailable
    {
        return new DisputeResolvedMail(
            $this->dispute,
            $this->contributor,
            $this->wasApproved,
            $this->adminNotes,
        );
    }
}
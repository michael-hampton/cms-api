<?php

namespace App\Services\OpenCollab\Notifications;

use App\Framework\Mail\Mailable;
use App\Framework\Notifications\EmailableNotification;
use App\Mail\OpenCollab\ChangesRequestedMail;
use App\Models\Page;
use App\Models\User;

final class ChangesRequestedNotification extends OpenCollabUserNotification
    implements EmailableNotification
{
    public function __construct(
        public readonly Page           $page,
        string $notes,
        public readonly User $contributor,
    )
    {
        parent::__construct(userId: $contributor->id, email: $contributor->email);
    }

    public function subject(): string
    {
        return "Changes requested for \"{$this->page->title}\"";
    }

    public function toMailable(): Mailable
    {
        return new ChangesRequestedMail($this->page, $this->contributor);
    }
}
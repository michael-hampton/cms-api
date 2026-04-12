<?php

namespace App\Services\OpenCollab;

use App\Events\OpenCollab\InvitationAccepted;
use App\Framework\Mail\MailManager;

class InvitationEmailChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly MailManager $mailManager
    )
    {
    }

    public function supports(object $event): bool
    {
        return $event instanceof InvitationAccepted;
    }

    public function send(object $event): bool
    {
        return true;
//        return $this->mailManager
//            ->to($event->user->email)
//            ->send(); //todo
    }
}
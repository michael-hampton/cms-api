<?php

namespace App\Framework\Notification;

use App\Framework\Support\Mail;

class NotificationService
{
    public function send($notifiable, Notification $notification): void
    {
        // Send email
        $mailData = $notification->toMail();
        Mail::send($mailData['to'], $mailData['subject'], $mailData['body']);

        // Save to database
        $dbData = $notification->toDatabase();
        // Save notification to notifications table
    }
}
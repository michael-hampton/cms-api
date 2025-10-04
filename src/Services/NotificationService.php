<?php

namespace App\Services;

class NotificationService
{
    public function notifyNewComment($comment): void
    {
        // Send notification to admin about new comment
        // This could be email, Slack, etc.
    }

    public function notifyEventSignup($signup): void
    {
        // Send notification to admin about new event signup
    }
}
<?php

namespace App\Framework\Notifications;

interface ConsentAwareNotification
{
    public function consentType(): string;
}
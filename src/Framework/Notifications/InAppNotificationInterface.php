<?php

namespace App\Framework\Notifications;

interface InAppNotificationInterface extends NotificationInterface
{
    public function body(): ?string;

    public function icon(): ?string;

    public function url(): ?string;

    public function tag(): ?string;
}
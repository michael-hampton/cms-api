<?php

namespace App\Services\OpenCollab;

interface NotificationChannelInterface
{
    public function supports(object $event): bool;

    public function send(object $event): bool;
}
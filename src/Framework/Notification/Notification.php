<?php

namespace App\Framework\Notification;

abstract class Notification
{
    abstract public function toMail(): array;
    abstract public function toDatabase(): array;
}

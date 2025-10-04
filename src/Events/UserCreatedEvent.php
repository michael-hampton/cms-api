<?php

namespace App\Events;

class UserCreatedEvent
{
    public $user;

    public function __construct(array $user)
    {
        $this->user = $user;
    }
}

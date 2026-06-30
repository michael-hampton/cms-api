<?php

namespace App\Services\OpenCollab\Notifications;

use App\Framework\Notifications\AbstractNotification;
use App\Framework\Notifications\UserRecipientNotification;

abstract class OpenCollabUserNotification extends AbstractNotification implements UserRecipientNotification
{
}

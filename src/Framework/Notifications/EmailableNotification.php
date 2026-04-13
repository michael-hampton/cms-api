<?php

namespace App\Framework\Notifications;

use App\Framework\Mail\Mailable;

/**
 * A notification that knows how to produce its own email.
 *
 * Implement this on any notification that should be delivered via email.
 * The EmailChannel will call toMailable() — it has no knowledge of
 * which mailable class is used or what domain it belongs to.
 */
interface EmailableNotification extends NotificationInterface
{
    public function toMailable(): Mailable;
}
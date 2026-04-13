<?php

namespace App\Framework\Notifications;

/**
 * A channel delivers notifications to a specific transport (email, log, push, etc.).
 *
 * Channels MUST NOT throw. Catch internally, log, and return false on failure
 * so the dispatcher can continue to other channels.
 */
interface ChannelInterface
{
    public function supports(NotificationInterface $notification): bool;

    /**
     * Returns true on success, false on failure (after logging internally).
     */
    public function send(NotificationInterface $notification): bool;
}
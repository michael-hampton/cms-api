<?php

namespace App\Framework\Notifications;

use App\Framework\Support\Logger;

/**
 * Dispatches a notification through all registered channels that support it.
 *
 * Rules:
 *   - New notification types need zero changes here.
 *   - New channels need zero changes here — register via the constructor.
 *   - Channel failures are logged but never rethrow.
 *   - The dispatcher always attempts every matching channel.
 */
class NotificationDispatcher
{
    /** @param ChannelInterface[] $channels */
    public function __construct(
        private readonly array  $channels,
        private readonly Logger $logger,
    )
    {
    }

    /**
     * Send through every supporting channel.
     * Returns the number of channels that succeeded.
     */
    public function dispatch(NotificationInterface $notification): int
    {
        $succeeded = 0;

        foreach ($this->channels as $channel) {
            if (!$channel->supports($notification)) {
                continue;
            }

            try {
                if ($channel->send($notification)) {
                    $succeeded++;
                }
            } catch (\Throwable $e) {
                $this->logger->error('Notification channel threw unexpectedly.', [
                    'channel' => get_class($channel),
                    'notification' => get_class($notification),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $succeeded;
    }
}
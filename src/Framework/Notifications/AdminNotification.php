<?php

namespace App\Framework\Notifications;

/**
 * Marker interface for notifications that target the admin team
 * rather than a specific contributor or user.
 *
 * Notifications implementing this interface should also implement
 * EmailableNotification so they can provide a mailable.
 *
 * The AdminEmailChannel resolves the delivery address from site
 * configuration — the notification itself does not carry an email.
 *
 * Example:
 *   final class DisputeRaisedNotification extends AbstractNotification
 *       implements EmailableNotification, AdminNotification { ... }
 */
interface AdminNotification extends NotificationInterface
{
}
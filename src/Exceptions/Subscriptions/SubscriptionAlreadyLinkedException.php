<?php

namespace App\Exceptions\Subscriptions;

/**
 * Thrown when a print subscription lookup succeeds but the record is
 * already claimed by a different member account.
 *
 * Carries the linked email address so the controller can mask and
 * surface it in the error message without the service knowing anything
 * about presentation.
 */
class SubscriptionAlreadyLinkedException extends \RuntimeException
{
    public function __construct(
        private readonly string $linkedEmail,
        string                  $message = 'Subscription is already linked to another account.',
        int                     $code = 0,
        ?\Throwable             $previous = null,
    )
    {
        parent::__construct($message, $code, $previous);
    }

    public function getLinkedEmail(): string
    {
        return $this->linkedEmail;
    }
}
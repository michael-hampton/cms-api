<?php

namespace App\Framework\Support\Config\Publishing;

use App\Framework\Support\Config\Storage\ConfigDocumentRecord;
use RuntimeException;

/**
 * Raised by ConfigPublishService::publish() when the document currently
 * in storage no longer matches the fingerprint the caller loaded — i.e.
 * someone else has published a change in the meantime.
 *
 * Carries the current stored record so the caller (an HTTP controller,
 * typically) can turn this into a 409-style response and, from Ticket 5
 * onward, hand it to ConfigConflictResolver for key-level reconciliation.
 */
final class ConcurrentModificationException extends RuntimeException
{
    public function __construct(public readonly ConfigDocumentRecord $currentRecord)
    {
        parent::__construct(sprintf(
            'Configuration "%s" was modified by someone else since it was loaded.',
            $currentRecord->type,
        ));
    }
}
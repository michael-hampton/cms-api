<?php

declare(strict_types=1);

namespace App\Enums\Subscriptions;

/**
 * Which export pipeline a PrintVendorConnection may be used for.
 *
 * A connection flagged Both is eligible to be selected as the default
 * for either the label pipeline (SftpLabelExportTransport) or the
 * batch/print pipeline (SftpPrintExportTransport).
 */
enum PrintVendorConnectionType: string
{
    case Label = 'label';
    case Batch = 'batch';
    case Both = 'both';

    /**
     * Whether a connection of this type may serve the given pipeline type.
     */
    public function supports(self $required): bool
    {
        if ($this === self::Both) {
            return true;
        }

        return $this === $required;
    }
}
<?php

declare(strict_types=1);

namespace App\Events\Subscriptions;

use App\DTO\Subscriptions\PrintOrder\PrintOrderResult;
use App\Models\IssueDelivery;

/**
 * Fired after a print order has been successfully generated for an issue.
 *
 * Listeners may use this for:
 *   - Notifying print suppliers
 *   - Sending admin summary emails
 *   - Triggering downstream export workflows
 */
final class PrintOrderGenerated
{
    public function __construct(
        public readonly IssueDelivery    $issueDelivery,
        public readonly PrintOrderResult $result,
    ) {}
}
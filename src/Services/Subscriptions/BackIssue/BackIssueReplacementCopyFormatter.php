<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\BackIssue;

use App\Models\SubscriptionIssueFulfilment;

/**
 * Builds the CSV payload sent to the vendor for a batch of BACK_ISSUE
 * fulfilments. Pure formatting — no querying, no writes, no dispatch.
 *
 * Kept separate from BackIssueReplacementCopyDispatchService so the export
 * shape can change (columns, format) without touching orchestration, and so
 * it can be swapped for a different strategy the same way
 * CsvProductExportFormatStrategy is swapped in the product pipeline.
 */
class BackIssueReplacementCopyFormatter
{
    private const HEADER = ['fulfilment_id', 'subscription_id', 'issue_delivery_id'];

    /**
     * @param SubscriptionIssueFulfilment[]|iterable $fulfilments
     */
    public function format(iterable $fulfilments): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, self::HEADER);

        foreach ($fulfilments as $fulfilment) {
            fputcsv($handle, [
                $fulfilment->id,
                $fulfilment->subscription_id,
                $fulfilment->issue_delivery_id,
            ]);
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return $contents;
    }

    public function extension(): string
    {
        return 'csv';
    }
}

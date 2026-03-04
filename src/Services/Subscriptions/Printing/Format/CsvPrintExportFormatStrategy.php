<?php

namespace App\Services\Subscriptions\Printing\Format;

use App\Models\PrintFulfillment;

class CsvPrintExportFormatStrategy implements PrintExportFormatStrategy
{
    private const HEADERS = [
        'batch_id',
        'subscription_id',
        'member_name',
        'address_line_1',
        'address_line_2',
        'city',
        'postcode',
        'country',
        'issue_id',
        'issue_title',
        'tracking_number',
    ];

    /**
     * @param PrintFulfillment[] $fulfillments
     */
    public function generate(int $batchId, array $fulfillments, array $issue): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new \RuntimeException('Failed to open in-memory buffer for CSV generation');
        }

        fputcsv($handle, self::HEADERS);

        foreach ($fulfillments as $fulfillment) {
            // Read each property into a local variable first.
            // Mockery partials proxy access through __get; applying ?? directly
            // to a __get result can silently return '' instead of the real value
            // because PHP evaluates the null-coalesce before __get can return.
            $addressLine2 = $fulfillment->address_line_2;
            $trackingNumber = $fulfillment->tracking_number;

            fputcsv($handle, [
                $batchId,
                $fulfillment->subscription_id,
                $fulfillment->full_name,
                $fulfillment->address_line_1,
                $addressLine2 !== null ? $addressLine2 : '',
                $fulfillment->city,
                $fulfillment->postcode,
                $fulfillment->country,
                $issue['id'],
                $issue['title'] !== null ? $issue['title'] : '',
                $trackingNumber !== null ? $trackingNumber : '',
            ]);
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        if ($contents === false) {
            throw new \RuntimeException('Failed to read CSV contents from buffer');
        }

        return $contents;
    }

    public function extension(): string
    {
        return 'csv';
    }
}
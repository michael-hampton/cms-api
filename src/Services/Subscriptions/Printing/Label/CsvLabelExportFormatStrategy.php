<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Printing\Label;

use App\Models\PrintFulfillment;

/**
 * Generates a single-row CSV label for one subscriber.
 *
 * The file is self-contained: it includes headers so the supplier
 * can import a single file without a schema reference. One file
 * per LabelRun (per subscriber).
 *
 * Column set (per spec):
 *   full_name, address_line_1, address_line_2, city, postcode, country,
 *   subscription_account_number, issue_number, issue_title,
 *   return_name, return_address_line_1, return_address_line_2,
 *   return_city, return_postcode, return_country
 */
class CsvLabelExportFormatStrategy implements LabelExportFormatStrategy
{
    private const HEADERS = [
        'full_name',
        'address_line_1',
        'address_line_2',
        'city',
        'postcode',
        'country',
        'subscription_account_number',
        'issue_number',
        'issue_title',
        'return_name',
        'return_address_line_1',
        'return_address_line_2',
        'return_city',
        'return_postcode',
        'return_country',
    ];

    public function generate(PrintFulfillment $fulfillment, LabelContext $context): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new \RuntimeException('Failed to open in-memory buffer for CSV label generation');
        }

        fputcsv($handle, self::HEADERS);

        // Read each property into a local variable first.
        // Mockery partials proxy access through __get; applying ?? directly
        // to a __get result can silently return '' instead of the real value.
        $addressLine2 = $fulfillment->address_line_2;

        fputcsv($handle, [
            $fulfillment->full_name,
            $fulfillment->address_line_1,
            $addressLine2 ?? '',
            $fulfillment->city,
            $fulfillment->postcode,
            $fulfillment->country,
            (string)$fulfillment->subscription_id,
            $context->issueNumber ?? '',
            $context->issueTitle ?? '',
            $context->returnName,
            $context->returnAddressLine1,
            $context->returnAddressLine2 ?? '',
            $context->returnCity,
            $context->returnPostcode,
            $context->returnCountry,
        ]);

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        if ($contents === false) {
            throw new \RuntimeException('Failed to read CSV label contents from buffer');
        }

        return $contents;
    }

    public function extension(): string
    {
        return 'csv';
    }

    public function formatName(): string
    {
        return 'csv';
    }
}
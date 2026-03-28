<?php

declare(strict_types=1);

namespace App\Services\Product\Fulfilment\Format;

use App\Models\ProductFulfilment;
use App\Services\Subscriptions\Printing\Format\PrintExportFormatStrategy;

/**
 * Generates a CSV export for a ProductBatch.
 *
 * Implements PrintExportFormatStrategy — the interface is reused unchanged
 * from the print pipeline. The $fulfillments parameter carries ProductFulfilment
 * instances, which have the same address fields as PrintFulfillment but add
 * SKU and quantity columns relevant to product fulfilment.
 *
 * PrintExportFormatStrategy is closed for modification. This is a new
 * implementation of the same interface for the product pipeline.
 */
class CsvProductExportFormatStrategy implements PrintExportFormatStrategy
{
    private const HEADERS = [
        'batch_id',
        'order_id',
        'order_line_id',
        'sku',
        'quantity',
        'full_name',
        'address_line_1',
        'address_line_2',
        'city',
        'postcode',
        'country',
        'territory_id',
        'tracking_number',
    ];

    /**
     * @param ProductFulfilment[] $fulfillments
     * @param array{id: int, title: string|null} $issue Ignored for products;
     *        kept to satisfy the interface contract shared with the print pipeline.
     */
    public function generate(int $batchId, array $fulfillments, array $issue): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new \RuntimeException('Failed to open in-memory buffer for product CSV generation');
        }

        fputcsv($handle, self::HEADERS);

        foreach ($fulfillments as $fulfilment) {
            $addressLine2 = $fulfilment->address_line_2;
            $trackingNumber = $fulfilment->tracking_number;
            $territoryId = $fulfilment->territory_id;

            fputcsv($handle, [
                $batchId,
                $fulfilment->order_id,
                $fulfilment->order_line_id,
                $fulfilment->sku,
                $fulfilment->quantity,
                $fulfilment->full_name,
                $fulfilment->address_line_1,
                $addressLine2 !== null ? $addressLine2 : '',
                $fulfilment->city,
                $fulfilment->postcode,
                $fulfilment->country,
                $territoryId !== null ? $territoryId : '',
                $trackingNumber !== null ? $trackingNumber : '',
            ]);
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        if ($contents === false) {
            throw new \RuntimeException('Failed to read product CSV contents from buffer');
        }

        return $contents;
    }

    public function extension(): string
    {
        return 'csv';
    }
}
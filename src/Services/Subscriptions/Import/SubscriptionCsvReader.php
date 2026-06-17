<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Import;

use App\DTO\Subscriptions\BulkSubscriptionImportRow;
use Generator;
use InvalidArgumentException;

final class SubscriptionCsvReader
{
    /** @return Generator<int, array{line:int,row:BulkSubscriptionImportRow}> */
    public function read(string $path): Generator
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException("CSV file is not readable: {$path}");
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException("Unable to open CSV file: {$path}");
        }

        try {
            $headers = fgetcsv($handle);
            if ($headers === false) {
                throw new InvalidArgumentException('CSV file is empty.');
            }

            $headers = array_map(static fn(string $header): string => strtolower(trim($header)), $headers);
            $line = 1;

            while (($values = fgetcsv($handle)) !== false) {
                ++$line;

                if ($values === [null] || $values === []) {
                    continue;
                }

                if (count($headers) !== count($values)) {
                    throw new InvalidArgumentException("CSV line {$line} has the wrong number of columns.");
                }

                yield ['line' => $line, 'row' => BulkSubscriptionImportRow::fromArray(array_combine($headers, $values))];
            }
        } finally {
            fclose($handle);
        }
    }
}

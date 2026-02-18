<?php

namespace App\Imports;

use App\DTO\ImportResult;
use App\Framework\Database\Database;
use RuntimeException;

abstract class BaseMerchantImport
{
    protected ImportResult $result;
    protected ImportOptions $importOptions;

    public function __construct(
        protected readonly Database  $database,
        protected readonly CsvParser $csvParser
    )
    {
        $this->result = new ImportResult();
    }

    public function setOptions(ImportOptions $importOptions)
    {
        $this->importOptions = $importOptions;
    }

    public function import(string $filePath): ImportResult
    {
        $rows = $this->csvParser->parse($filePath);

        $this->database->transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $line = (int)($row['__line'] ?? 0);

                if (!empty($row['__malformed'])) {
                    $this->result->recordSkipped($line, $row, 'Row column count does not match header.');
                    continue;
                }

                try {
                    $this->validateRequiredColumns($row);
                    $this->importRow($row);
                    $this->result->recordImported();
                } catch (SkippableRowException $e) {
                    $this->result->recordSkipped($line, $row, $e->getMessage());
                } catch (RuntimeException $e) {
                    // Critical failure: bubble up and roll back the whole import.
                    throw $e;
                }
            }
        });

        return $this->result;
    }

    protected function validateRequiredColumns(array $row): void
    {
        foreach ($this->requiredColumns() as $column) {
            if (!array_key_exists($column, $row) || trim((string)$row[$column]) === '') {
                throw new SkippableRowException("Missing required field: {$column}");
            }
        }
    }

    abstract protected function requiredColumns(): array;

    abstract protected function importRow(array $row): void;

    protected function parseDate(string $value, string $field): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', trim($value))
            ?: \DateTimeImmutable::createFromFormat('d/m/Y', trim($value))
                ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', trim($value));

        if ($date === false) {
            throw new SkippableRowException("Invalid date format for field '{$field}': {$value}");
        }

        return $date;
    }

    protected function parseNonNegativeFloat(string $value, string $field): float
    {
        if (!is_numeric($value)) {
            throw new SkippableRowException("Field '{$field}' must be numeric, got: {$value}");
        }

        $float = (float)$value;

        if ($float < 0) {
            throw new SkippableRowException("Field '{$field}' must be >= 0, got: {$value}");
        }

        return $float;
    }

    protected function parseNonNegativeInt(string $value, string $field): int
    {
        if (!ctype_digit($value)) {
            throw new SkippableRowException("Field '{$field}' must be a non-negative integer, got: {$value}");
        }

        return (int)$value;
    }
}
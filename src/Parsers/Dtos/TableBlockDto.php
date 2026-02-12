<?php

namespace App\Parsers\Dtos;

use InvalidArgumentException;

final class TableBlockDto extends BaseBlockDto
{
    private const KNOWN_KEYS = ['hasHeader', 'rows'];

    public function __construct(
        public bool  $hasHeader,
        public array $rows
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'hasHeader' => false,
            'rows' => []
        ]);

        if (!is_array($data['rows']) || empty($data['rows'])) {
            throw new InvalidArgumentException('Table rows are required');
        }

        $rows = [];

        foreach ($data['rows'] as $row) {
            if (!is_array($row)) {
                // skip invalid row
                continue;
            }

            // Backwards compatibility: numeric array → old format
            if (array_keys($row) === range(0, count($row) - 1)) {
                $parsedRow = array_map('trim', $row);
                $rows[] = [
                    'cells' => $parsedRow,
                    'is_header' => false,
                ];
                continue;
            }

            // New format: associative row with 'cells' key
            if (isset($row['cells']) && is_array($row['cells'])) {
                $parsedRow = array_map('trim', $row['cells']);
                $rows[] = [
                    'cells' => $parsedRow,
                    'is_header' => !empty($row['is_header']),
                ];
            }
        }

        if (empty($rows)) {
            throw new InvalidArgumentException('At least one non-empty row is required');
        }

        return new self((bool)$data['hasHeader'], $rows);
    }


    public function getTotalWordCount(): int
    {
        $total = 0;

        foreach ($this->rows as $row) {
            // Backwards-compatible: numeric arrays
            $cells = $row['cells'] ?? (is_array($row) ? $row : []);

            foreach ($cells as $cell) {
                if (!is_string($cell)) {
                    continue; // skip non-strings just in case
                }
                $total += str_word_count(strip_tags($cell));
            }
        }

        return $total;
    }

    public function toArray(): array
    {
        return [
            'hasHeader' => $this->hasHeader,
            'rows' => $this->rows,
            'row_count' => count($this->rows),
            'column_count' => $this->getColumnCount(),
            'cell_count' => $this->getCellCount(),
            'total_word_count' => $this->getTotalWordCount()
        ];
    }

    public function getType(): string
    {
        return 'table';
    }

    public function getColumnCount(): int
    {
        if (empty($this->rows)) {
            return 0;
        }

        return max(array_map(fn($r) => count($r['cells']), $this->rows));
    }

    public function getCellCount(): int
    {
        return array_sum(array_map(fn($r) => count($r['cells']), $this->rows));
    }
}
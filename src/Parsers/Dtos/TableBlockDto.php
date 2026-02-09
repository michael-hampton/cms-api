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
                continue;
            }

            $parsedRow = [];
            foreach ($row as $cell) {
                $parsedRow[] = trim($cell);
            }

            if (!empty($parsedRow)) {
                $rows[] = $parsedRow;
            }
        }

        if (empty($rows)) {
            throw new InvalidArgumentException('At least one non-empty row is required');
        }

        return new self((bool)$data['hasHeader'], $rows);
    }

    public function getColumnCount(): int
    {
        if (empty($this->rows)) {
            return 0;
        }
        return max(array_map('count', $this->rows));
    }

    public function getCellCount(): int
    {
        $total = 0;
        foreach ($this->rows as $row) {
            $total += count($row);
        }
        return $total;
    }

    public function getTotalWordCount(): int
    {
        $total = 0;
        foreach ($this->rows as $row) {
            foreach ($row as $cell) {
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
}
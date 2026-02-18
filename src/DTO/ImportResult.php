<?php

namespace App\DTO;

final class ImportResult
{
    private int $imported = 0;
    private array $skipped = [];

    public function recordImported(): void
    {
        $this->imported++;
    }

    public function recordSkipped(int $line, array $row, string $reason): void
    {
        $this->skipped[] = [
            'line' => $line,
            'row' => $row,
            'reason' => $reason,
        ];
    }

    public function importedCount(): int
    {
        return $this->imported;
    }

    public function skippedRows(): array
    {
        return $this->skipped;
    }

    public function skippedCount(): int
    {
        return count($this->skipped);
    }
}
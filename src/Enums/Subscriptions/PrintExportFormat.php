<?php

namespace App\Enums\Subscriptions;

enum PrintExportFormat: string
{
    case CSV = 'csv';

    public function mimeType(): string
    {
        return match ($this) {
            self::CSV => 'text/csv',
        };
    }
}
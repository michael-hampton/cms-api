<?php

declare(strict_types=1);

namespace App\Enums\Subscriptions;

enum LabelExportFormat: string
{
    case Pdf = 'pdf';
    case Csv = 'csv';

    public function extension(): string
    {
        return $this->value;
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Pdf => 'application/pdf',
            self::Csv => 'text/csv',
        };
    }
}
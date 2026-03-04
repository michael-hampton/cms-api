<?php

namespace App\Services\Subscriptions\Printing\Format;

use App\Models\Printing\PrintFulfillment;

interface PrintExportFormatStrategy
{
    /**
     * Generate export file contents from the given fulfillments.
     *
     * @param int $batchId
     * @param PrintFulfillment[] $fulfillments
     * @param array{id: int, title: string|null} $issue Issue metadata snapshot.
     *
     * @return string Raw file contents.
     */
    public function generate(int $batchId, array $fulfillments, array $issue): string;

    /**
     * File extension for this format (e.g. "csv").
     */
    public function extension(): string;
}
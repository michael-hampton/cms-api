<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Printing\Label;

use App\Models\LabelRun;
use App\Models\PrintFulfillment;

/**
 * Strategy contract for label file generation.
 *
 * Each implementation owns the full rendering of a single subscriber's
 * label. The output is a raw string — transport is handled separately.
 *
 * Label content (per spec):
 *   - Full name + delivery address
 *   - Issue number
 *   - Issue title
 *   - Subscription account number
 *   - Return address
 *
 * Implementations:
 *   - CsvLabelExportFormatStrategy
 *   - PdfLabelExportFormatStrategy
 */
interface LabelExportFormatStrategy
{
    /**
     * Generate the label file contents for a single fulfillment.
     *
     * @param PrintFulfillment $fulfillment The subscriber's fulfilment record.
     * @param LabelContext $context Issue metadata + return address.
     *
     * @return string Raw file contents ready for transport upload.
     *
     * @throws \RuntimeException On generation failure.
     */
    public function generate(PrintFulfillment $fulfillment, LabelContext $context): string;

    /**
     * File extension for this format (e.g. "csv", "pdf").
     */
    public function extension(): string;

    /**
     * Format identifier stored on LabelRun for observability.
     */
    public function formatName(): string;
}
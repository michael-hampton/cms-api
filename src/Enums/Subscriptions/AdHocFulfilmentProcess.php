<?php

declare(strict_types=1);

namespace App\Enums\Subscriptions;

/**
 * Discriminator for which underlying fulfilment file pipeline an
 * AdHocFulfilmentRequest was generated against.
 *
 * Phase 1 only wires up PRINT_BATCH (the existing PrintBatch export
 * pipeline). Additional cases (e.g. LABEL_RUN) should be added only once
 * the corresponding trigger/download services are actually threaded
 * through AdHocFulfilmentGenerationService — do not add cases speculatively.
 */
enum AdHocFulfilmentProcess: string
{
    case PRINT_BATCH = 'print_batch';

    public function label(): string
    {
        return match ($this) {
            self::PRINT_BATCH => 'Print batch export',
        };
    }
}
<?php

declare(strict_types=1);

namespace App\Events\Subscriptions;

use App\Models\LabelRun;

/**
 * Fired after a LabelRun transitions to Complete.
 *
 * Listeners may use this for:
 *   - Observability / metrics ("how many labels generated?")
 *   - Downstream supplier notification
 *   - Batch completion checks
 */
final class LabelRunGenerated
{
    public function __construct(
        public readonly LabelRun $labelRun,
    )
    {
    }
}
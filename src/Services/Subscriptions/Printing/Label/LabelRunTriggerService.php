<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Printing\Label;

use App\Framework\Queue\Dispatcher;
use App\Jobs\Subscriptions\GenerateLabelJob;
use App\Models\LabelRun;

/**
 * Manually (re-)triggers generation for a single LabelRun, via the API.
 *
 * State validation (LabelRun::canTriggerGeneration()) is the controller's
 * responsibility — this service assumes it has already been checked and
 * focuses solely on the dispatch workflow. Generation itself (and the
 * pending → generating → complete|failed lifecycle) lives in
 * LabelGenerationService, reached via the queued GenerateLabelJob.
 */
class LabelRunTriggerService
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
    )
    {
    }

    public function trigger(LabelRun $labelRun): void
    {
        $this->dispatcher
            ->dispatch(GenerateLabelJob::for($labelRun->id))
            ->onQueue('print');
    }
}

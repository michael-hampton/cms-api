<?php

declare(strict_types=1);

namespace App\Jobs\Subscriptions;

use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Subscriptions\LabelRunRepository;
use App\Services\Subscriptions\Printing\Label\LabelGenerationService;

/**
 * Processes a single LabelRun.
 *
 * Thin job — all logic lives in LabelGenerationService.
 * Retries on failure with backoff; LabelGenerationService marks the
 * LabelRun failed before re-throwing so every attempt is observable.
 *
 * After max attempts the job is dead-lettered. Operators can inspect
 * the LabelRun.failure_reason and LabelRun.attempt_count to diagnose.
 */
class GenerateLabelJob extends BaseJob
{
    public string $queue = 'print';
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct()
    {
    }

    public function handle(
        LabelRunRepository     $labelRunRepository,
        LabelGenerationService $labelGenerationService,
        Logger                 $logger,
        int                    $labelRunId,
    ): void
    {
        $labelRun = $labelRunRepository->find($labelRunId);

        if (!$labelRun) {
            $logger->error('GenerateLabelJob: LabelRun not found', [
                'label_run_id' => $labelRunId,
            ]);
            return;
        }

        $labelGenerationService->generate($labelRun);
    }
}
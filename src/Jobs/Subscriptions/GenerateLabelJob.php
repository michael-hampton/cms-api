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
    public ?string $queue = 'print';
    public int $tries = 3;
    public int $backoff = 60;
    private LabelRunRepository $labelRunRepository;
    private LabelGenerationService $labelGenerationService;
    private Logger $logger;

    public function __construct(
        private readonly int $labelRunId,
    )
    {
    }

    public function handle(): void
    {
        $labelRun = $this->labelRunRepository->find($this->labelRunId);

        if (!$labelRun) {
            $this->logger->error('GenerateLabelJob: LabelRun not found', [
                'label_run_id' => $this->labelRunId,
            ]);
            return;
        }

        $this->labelGenerationService->generate($labelRun);
    }
}
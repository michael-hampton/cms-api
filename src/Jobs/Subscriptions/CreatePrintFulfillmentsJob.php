<?php

declare(strict_types=1);

namespace App\Jobs\Subscriptions;

use App\DTO\Subscriptions\WorkflowStageResult;
use App\Enums\Subscriptions\SubscriptionType;
use App\Enums\Workflow\WorkflowRunStatus;
use App\Events\Subscriptions\AllFulfilmentsCreated;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;
use App\Repositories\Subscriptions\PrintRunRepository;
use App\Services\Workflow\WorkflowRunRecorderFactory;

class CreatePrintFulfillmentsJob extends BaseJob
{
    public ?string $queue = 'print';
    public int $tries = 3;
    public int $backoff = 30;

    private PrintRunRepository $printRunRepository;
    private IssueDeliveryRepository $issueDeliveryRepository;
    private IssuesDeliveredRepository $issuesDeliveredRepository;
    private WorkflowRunRecorderFactory $recorderFactory;
    private Logger $logger;

    public function __construct(
        private readonly int $printRunId,
        private readonly int $issueDeliveryId,
    )
    {
    }

    public function handle(): void
    {
        $printRun = $this->printRunRepository->find($this->printRunId);

        if (!$printRun) {
            $this->logger->error('CreatePrintFulfillmentsJob: PrintRun not found', [
                'print_run_id' => $this->printRunId,
            ]);
            return;
        }

        if ($printRun->isCancelled()) {
            $this->logger->info('CreatePrintFulfillmentsJob: PrintRun cancelled, aborting', [
                'print_run_id' => $this->printRunId,
            ]);
            return;
        }

        $issueDelivery = $this->issueDeliveryRepository->find($this->issueDeliveryId);

        if (!$issueDelivery) {
            $this->logger->error('CreatePrintFulfillmentsJob: IssueDelivery not found', [
                'print_run_id' => $this->printRunId,
                'issue_delivery_id' => $this->issueDeliveryId,
            ]);

            $printRun->markFailed();

            $this->recorderFactory
                ->forPrintRun($printRun, 'phase_1', WorkflowRunStatus::BATCHING)
                ->record(WorkflowStageResult::failed(
                    'IssueDelivery not found: ' . $this->issueDeliveryId,
                    ['print_run_id' => $this->printRunId],
                ));

            return;
        }

        $subscriptionIds = $this->issuesDeliveredRepository
            ->getDispatchedSubscriptionIdsForIssue($issueDelivery->id);

        $printSubscriptions = empty($subscriptionIds)
            ? collect([])
            : Subscription::whereIn('id', $subscriptionIds)
                ->where('delivery_type', SubscriptionType::PRINTED->value)
                ->orderBy('id', 'asc')
                ->get();

        $chunkSize = (int)config('print.chunk_size', 200);
        $chunks = $printSubscriptions->chunk($chunkSize);
        $totalChunks = $chunks->count();

        if ($totalChunks === 0) {
            $printRun->markFulfilling(0);
            $printRun->markBatching();

            event(new AllFulfilmentsCreated(
                printRun: $printRun,
                totalFulfilments: 0,
            ));

            $this->recorderFactory
                ->forPrintRun($printRun, 'phase_1', WorkflowRunStatus::BATCHING)
                ->record(WorkflowStageResult::succeeded([
                    'total_chunks' => 0,
                    'total_fulfilments' => 0,
                    'skipped_reason' => 'No dispatchable print fulfilments',
                ]));

            $this->logger->info('CreatePrintFulfillmentsJob: no dispatchable print fulfilments', [
                'print_run_id' => $this->printRunId,
                'issue_delivery_id' => $this->issueDeliveryId,
            ]);

            return;
        }

        $printRun->markFulfilling($totalChunks);

        foreach ($chunks as $chunkIndex => $chunk) {
            dispatch(CreateFulfilmentsChunkJob::for(
                $this->printRunId,
                $this->issueDeliveryId,
                $chunk->pluck('id')->toArray(),
                $chunkIndex,
            ))->onQueue('print');
        }

        dispatch(FulfilmentCompletionMonitorJob::for($this->printRunId))->onQueue('print');

        $this->logger->info('CreatePrintFulfillmentsJob: chunk jobs dispatched', [
            'print_run_id' => $this->printRunId,
            'issue_delivery_id' => $this->issueDeliveryId,
            'subscription_count' => $printSubscriptions->count(),
            'total_chunks' => $totalChunks,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs\Subscriptions;

use App\Actions\Subscriptions\Print\CreatePrintFulfillmentAction;
use App\Events\Subscriptions\AllFulfilmentsCreated;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\PrintRunRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

class CreateFulfilmentsChunkJob extends BaseJob
{
    public ?string $queue = 'print';
    public int $tries = 3;
    public int $backoff = 60;
    private PrintRunRepository $printRunRepository;
    private IssueDeliveryRepository $issueDeliveryRepository;
    private SubscriptionRepository $subscriptionRepository;
    private CreatePrintFulfillmentAction $fulfillmentAction;
    private Logger $logger;

    public function __construct(
        private readonly int $printRunId,
        private readonly int $issueDeliveryId,
        private readonly array $subscriptionIds,
        private readonly int $chunkIndex,
    )
    {
    }

    public function subscriptionIds(): array
    {
        return $this->subscriptionIds;
    }

    public function chunkIndex(): int
    {
        return $this->chunkIndex;
    }

    public function handle(): void
    {
        $printRun = $this->printRunRepository->find($this->printRunId);

        if (!$printRun) {
            $this->logger->error('CreateFulfilmentsChunkJob: PrintRun not found', [
                'print_run_id' => $this->printRunId,
                'chunk_index' => $this->chunkIndex,
            ]);
            return;
        }

        if ($printRun->isCancelled()) {
            $this->logger->info('CreateFulfilmentsChunkJob: PrintRun cancelled, skipping chunk', [
                'print_run_id' => $this->printRunId,
                'chunk_index' => $this->chunkIndex,
            ]);
            return;
        }

        $issueDelivery = $this->issueDeliveryRepository->find($this->issueDeliveryId);

        if (!$issueDelivery) {
            $this->logger->error('CreateFulfilmentsChunkJob: IssueDelivery not found', [
                'issue_delivery_id' => $this->issueDeliveryId,
                'print_run_id' => $this->printRunId,
            ]);
            return;
        }

        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($this->subscriptionIds as $subscriptionId) {
            $subscription = $this->subscriptionRepository->find($subscriptionId);

            if (!$subscription) {
                $this->logger->warning('CreateFulfilmentsChunkJob: subscription not found', [
                    'subscription_id' => $subscriptionId,
                    'print_run_id' => $this->printRunId,
                ]);
                $failed++;
                continue;
            }

            try {
                $this->fulfillmentAction->execute($subscription, $issueDelivery);
                $created++;
            } catch (\Throwable $exception) {
                $failed++;
                $this->logger->error('CreateFulfilmentsChunkJob: fulfillment creation failed', [
                    'subscription_id' => $subscriptionId,
                    'issue_delivery_id' => $this->issueDeliveryId,
                    'print_run_id' => $this->printRunId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->logger->info('CreateFulfilmentsChunkJob: chunk processed', [
            'print_run_id' => $this->printRunId,
            'chunk_index' => $this->chunkIndex,
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);

        $newCount = $printRun->incrementFulfilledChunks($this->chunkIndex);

        if ($printRun->allChunksComplete()) {
            $this->logger->info('CreateFulfilmentsChunkJob: all chunks complete, firing AllFulfilmentsCreated', [
                'print_run_id' => $this->printRunId,
                'total_chunks' => $printRun->total_chunks,
                'fulfilled_count' => $newCount,
            ]);

            event(new AllFulfilmentsCreated(
                printRun: $printRun,
                totalFulfilments: $created,
            ));
        }
    }
}

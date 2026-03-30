<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Jobs\Subscriptions\CreateFulfilmentsChunkJob;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\PrintRunRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

class PrintRedispatchChunks extends Command
{
    use ReportsCommandResult;

    const FAILURE = 0;
    const SUCCESS = 1;
    public $description = 'Re-generate and export a print batch.';
    protected $signature = 'print:redispatch-chunks {batchId : The ID of the print batch to export}';

    public function __construct(
        private readonly PrintRunRepository      $printRunRepository,
        private readonly IssueDeliveryRepository $issueDeliveryRepository,
        private readonly SubscriptionRepository  $subscriptionRepository,
    )
    {
    }

    public function handle(): int
    {
        $result = $this->createResult('print:redispatch-chunks');
        $printRun = $this->printRunRepository->find($this->argument('printRunId'));
        $missing = $printRun->getMissingChunkIndexes();

        if (empty($missing)) {
            $this->info('No missing chunks.');
            return self::SUCCESS;
        }

        try {
            $issueDelivery = $this->issueDeliveryRepository->find($printRun->issue_delivery_id);
            $referenceDate = $issueDelivery->on_sale_date ?? new \DateTime();

            $subscriptions = $this->subscriptionRepository->findPrintSubscriptionsForIssueDelivery(
                $issueDelivery->id,
                $issueDelivery->subscription_plan_id,
                $referenceDate,
            );

            $chunks = $subscriptions->chunk(config('print.chunk_size', 200));

            foreach ($missing as $chunkIndex) {
                $chunk = $chunks[$chunkIndex] ?? null;

                if (!$chunk) {
                    $this->warn("Chunk index {$chunkIndex} not found in current subscription set.");
                    continue;
                }

                dispatch(CreateFulfilmentsChunkJob::for(),
                    $printRun->id,
                    $issueDelivery->id,
                    $chunk->pluck('id')->toArray(),
                    $chunkIndex,
                );

                $this->info("Re-dispatched chunk {$chunkIndex}.");
            }

            $result->incrementSucceeded();
            $result->addMessage("Re-dispatched chunk {$chunkIndex}.");
        } catch (\Throwable $e) {
            $this->reportFailure(
                result: $result,
                message: "Export failed for chunk #{$chunkIndex}: {$e->getMessage()}",
                context: ['chunk_index' => $chunkIndex],
                throwable: $e
            );
        }

        $this->reportResult($result);
        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}
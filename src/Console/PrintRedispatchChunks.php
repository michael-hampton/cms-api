<?php

namespace App\Console;

use App\Enums\Subscriptions\SubscriptionType;
use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Jobs\Subscriptions\CreateFulfilmentsChunkJob;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Repositories\Subscriptions\PrintRunRepository;

class PrintRedispatchChunks extends Command
{
    use ReportsCommandResult;

    const FAILURE = 0;
    const SUCCESS = 1;
    public $description = 'Re-generate and export a print batch.';
    protected $signature = 'print:redispatch-chunks {batchId : The ID of the print batch to export}';

    public function __construct(
        private readonly PrintRunRepository $printRunRepository,
        private readonly IssueDeliveryRepository $issueDeliveryRepository,
        private readonly SubscriptionIssueFulfilmentRepository $subscriptionIssueFulfilmentRepository,
    )
    {
    }

    public function handle(): int
    {
        $result = $this->createResult('print:redispatch-chunks');
        $batchId = (int)$this->argument('batchId');
        $printRun = $this->printRunRepository->find($batchId);

        if (!$printRun) {
            $this->error("Print run #{$batchId} not found.");
            return self::FAILURE;
        }

        $missing = $printRun->getMissingChunkIndexes();

        if (empty($missing)) {
            $this->info('No missing chunks.');
            return self::SUCCESS;
        }

        $chunkIndex = null;

        try {
            $issueDelivery = $this->issueDeliveryRepository->find($printRun->issue_delivery_id);
            $subscriptionIds = $this->subscriptionIssueFulfilmentRepository
                ->getDispatchedSubscriptionIdsForIssue((int) $issueDelivery->id);

            $subscriptions = empty($subscriptionIds)
                ? collect([])
                : Subscription::whereIn('id', $subscriptionIds)
                    ->where('delivery_type', SubscriptionType::PRINTED->value)
                    ->orderBy('id', 'asc')
                    ->get();

            $chunks = $subscriptions->chunk(config('print.chunk_size', 200));

            foreach ($missing as $chunkIndex) {
                $chunk = $chunks->get($chunkIndex) ?? null;

                if (!$chunk) {
                    $this->warn("Chunk index {$chunkIndex} not found in persisted fulfilment set.");
                    continue;
                }

                dispatch(CreateFulfilmentsChunkJob::for(
                    (int)$printRun->id,
                    (int)$issueDelivery->id,
                    $chunk->pluck('id')->toArray(),
                    (int)$chunkIndex,
                ))->dispatchNow();

                $this->info("Re-dispatched chunk {$chunkIndex}.");
                $result->incrementSucceeded();
                $result->addMessage("Re-dispatched chunk {$chunkIndex}.");
            }
        } catch (\Throwable $e) {
            $context = [];
            $message = 'Export failed';

            if ($chunkIndex !== null) {
                $context['chunk_index'] = $chunkIndex;
                $message .= " for chunk #{$chunkIndex}";
            }

            $this->reportFailure(
                result: $result,
                message: "{$message}: {$e->getMessage()}",
                context: $context,
                throwable: $e
            );
        }

        $this->reportResult($result);
        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}

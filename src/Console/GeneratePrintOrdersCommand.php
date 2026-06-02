<?php

declare(strict_types=1);

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Jobs\Subscriptions\GeneratePrintOrderJob;
use App\Repositories\Subscriptions\PrintOrderRepository;

/**
 * Dispatches GeneratePrintOrderJob for every IssueDelivery whose
 * print_order_date is today (or the supplied date) and has not yet
 * had its print order generated.
 *
 * Scheduling example (Kernel.php):
 *   $schedule->command(GeneratePrintOrdersCommand::class)->dailyAt('06:00');
 *
 * Usage:
 *   php artisan print:generate-orders
 *   php artisan print:generate-orders --date=2024-03-15
 *   php artisan print:generate-orders --issue_delivery_id=42   # single issue
 */
class GeneratePrintOrdersCommand extends Command
{
    use ReportsCommandResult;

    const SUCCESS = 1;
    const FAILURE = 0;

    protected $signature = 'print:generate-orders
        {--date=          : Date to process (Y-m-d). Defaults to today.}
        {--issue_delivery_id= : Process a single issue delivery by ID.}
        {--force          : Re-generate even if print_order_done is already true.}';

    public $description = 'Dispatches print order generation jobs for issues due today.';

    public function __construct(
        private readonly PrintOrderRepository $repository,
    ) {}

    public function handle(): int
    {
        $result = $this->createResult('print:generate-orders');

        $singleId = $this->option('issue_delivery_id')
            ? (int) $this->option('issue_delivery_id')
            : null;

        $date = $this->resolveDate();

        if ($singleId !== null) {
            return $this->processSingle($singleId, $result);
        }

        $issues = $this->repository->findDueForPrintOrder($date);

        if ($issues->isEmpty()) {
            $this->info('No issues due for print order generation.');
            return self::SUCCESS;
        }

        foreach ($issues as $issue) {
            try {
                dispatch(GeneratePrintOrderJob::for($issue->id))->onQueue('print');

                $result->incrementSucceeded();
                $result->addMessage(
                    "Dispatched print order job for issue delivery #{$issue->id} "
                    . "(issue: {$issue->issue_title}, print_order_date: {$issue->print_order_date})"
                );
            } catch (\Throwable $e) {
                $this->reportFailure(
                    result:    $result,
                    message:   "Failed to dispatch for issue delivery #{$issue->id}: {$e->getMessage()}",
                    context:   ['issue_delivery_id' => $issue->id],
                    throwable: $e,
                );
            }
        }

        $this->reportResult($result);

        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function processSingle(int $issueDeliveryId, mixed $result): int
    {
        try {
            dispatch(GeneratePrintOrderJob::for($issueDeliveryId))->onQueue('print');

            $result->incrementSucceeded();
            $result->addMessage("Dispatched print order job for issue delivery #{$issueDeliveryId}");
        } catch (\Throwable $e) {
            $this->reportFailure(
                result:    $result,
                message:   "Failed to dispatch for issue delivery #{$issueDeliveryId}: {$e->getMessage()}",
                context:   ['issue_delivery_id' => $issueDeliveryId],
                throwable: $e,
            );
        }

        $this->reportResult($result);

        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    private function resolveDate(): \DateTimeImmutable
    {
        $dateOption = $this->option('date');

        if ($dateOption) {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $dateOption);

            if (!$parsed) {
                $this->error("Invalid date format: {$dateOption}. Expected Y-m-d.");
                exit(self::FAILURE);
            }

            return $parsed;
        }

        return new \DateTimeImmutable('today');
    }
}
<?php

namespace App\Services\Subscriptions\Printing;

use App\DTO\Subscriptions\PrintRunWorkflowInput;
use App\Enums\Subscriptions\PrintRunStatus;
use App\Events\Subscriptions\PrintRunWorkflowNoData;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\IssueDelivery;
use App\Models\Model;
use App\Models\PrintRun;
use App\Models\WorkflowRun;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Repositories\Subscriptions\Printing\PrintProcessConfigRepository;
use App\Repositories\Subscriptions\PrintRunRepository;
use App\Services\Subscriptions\Printing\Driver\PrintDriverRegistry;
use DomainException;

/**
 * Orchestrates the creation of PrintRun records for one or more IssueDeliveries.
 *
 * Workflow steps
 * ──────────────
 *   1. Resolve process config — throws DomainException if missing/invalid.
 *   2. Create WorkflowRun audit record (status: running).
 *   3. Extract IssueDeliveries to process (from input or config query).
 *   4. No-data path: if none found, mark WorkflowRun no_data + notify, return.
 *   5. Cancel any pending PrintRuns for those issues (idempotency guard).
 *   6. For each issue:
 *        a. Ask driver: regional or non-regional?
 *        b. Create one PrintRun (pending) inside a transaction.
 *        c. Use BatchBuilderService to create/locate PrintBatch rows under it.
 *   7. Mark all new PrintRuns complete.
 *   8. If driver_sync_enabled: syncToDriver for each PrintRun.
 *   9. Mark WorkflowRun complete with summary.
 *  10. Notify (event).
 *
 * Failure handling
 * ────────────────
 *   - DomainException (config / driver problems): WorkflowRun → no_data, notify.
 *   - RuntimeException inside per-issue processing: that PrintRun → failed,
 *     logged, workflow continues for remaining issues (partial-success design).
 *   - Unrecoverable infrastructure failure: WorkflowRun → failed, rethrow.
 *
 * This class orchestrates. It does not:
 *   - Build queries
 *   - Format data for export
 *   - Access sessions or request globals
 */
class PrintRunWorkflow implements PrintRunWorkflowInterface
{
    public function __construct(
        private readonly PrintProcessConfigRepository $processConfigRepository,
        private readonly IssueDeliveryRepository      $issueDeliveryRepository,
        private readonly PrintRunRepository           $printRunRepository,
        private readonly PrintBatchRepository         $batchRepository,
        private readonly BatchBuilderService          $batchBuilderService,
        private readonly PrintDriverRegistry          $driverRegistry,
        private readonly Database                     $database,
        private readonly Logger                       $logger,
        private readonly WorkflowRunFactory           $workflowRunFactory
    )
    {
    }

    public function execute(PrintRunWorkflowInput $input): Model
    {
        // ── Step 1: Resolve process config ────────────────────────────────────
        // DomainException here means misconfiguration — surface via no_data path
        // rather than letting it propagate as an unhandled exception.
        try {
            $config = $this->processConfigRepository->findOrFail($input->processConfigId);
            $driver = $this->driverRegistry->get($config->driver);
        } catch (DomainException $e) {
            // WorkflowRun cannot be created yet — log and rethrow so the caller
            // (job, CLI) can record the failure at a higher level.
            $this->logger->error('PrintRunWorkflow: process config resolution failed', [
                'process_config_id' => $input->processConfigId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        // ── Step 2: Create WorkflowRun ─────────────────────────────────────────
        $workflowRun = $this->workflowRunFactory->create($input);

        $this->logger->info('PrintRunWorkflow: started', [
            'workflow_run_id' => $workflowRun->id,
            'process_config_id' => $input->processConfigId,
        ]);

        try {
            // ── Step 3: Extract IssueDeliveries ───────────────────────────────
            $issueDeliveries = $this->resolveIssueDeliveries($input, $config);

            // ── Step 4: No-data path ───────────────────────────────────────────
            if ($issueDeliveries->isEmpty()) {
                $workflowRun->markNoData(['reason' => 'No eligible issue deliveries found']);

                $this->logger->info('PrintRunWorkflow: no eligible issue deliveries', [
                    'workflow_run_id' => $workflowRun->id,
                ]);

                event(new PrintRunWorkflowNoData($workflowRun));

                return $workflowRun;
            }

            // ── Step 5: Cancel pending PrintRuns for these issues ──────────────
            if (!$input->dryRun) {
                $this->cancelPrecedingPrintRuns($issueDeliveries);
            }

            // ── Steps 6–7: Create PrintRun + batches per issue ─────────────────
            $printRuns = [];
            $failedIssueIds = [];

            foreach ($issueDeliveries as $issueDelivery) {
                try {
                    $isRegional = $input->forceRegional || $driver->isRegional($issueDelivery);
                    $printRun = $this->createPrintRunWithBatches(
                        $issueDelivery,
                        $workflowRun,
                        $isRegional,
                        $config->driver_sync_enabled,
                        $input->dryRun,
                    );
                    $printRuns[] = $printRun;
                } catch (\RuntimeException $e) {
                    // Per-issue failure is non-fatal: log, mark failed, continue.
                    $failedIssueIds[] = $issueDelivery->id;
                    $this->logger->error('PrintRunWorkflow: issue processing failed', [
                        'workflow_run_id' => $workflowRun->id,
                        'issue_delivery_id' => $issueDelivery->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (!$input->dryRun) {
                // ── Step 7: Mark all successful PrintRuns complete ─────────────
                foreach ($printRuns as $printRun) {
                    $printRun->markComplete();
                }

                // ── Step 8: Driver sync ────────────────────────────────────────
                if ($config->driver_sync_enabled) {
                    $this->syncPrintRunsToDriver($printRuns, $driver, $workflowRun);
                }
            }

            // ── Step 9: Mark WorkflowRun complete ─────────────────────────────
            $summary = [
                'issues_processed' => count($issueDeliveries),
                'print_runs_created' => count($printRuns),
                'failed_issue_ids' => $failedIssueIds,
                'dry_run' => $input->dryRun,
            ];

            $workflowRun->markComplete($summary);

            $this->logger->info('PrintRunWorkflow: completed', array_merge(
                ['workflow_run_id' => $workflowRun->id],
                $summary
            ));

            // ── Step 10: Notify ────────────────────────────────────────────────
            event(new \App\Events\Subscriptions\PrintRunWorkflowCompleted($workflowRun));

        } catch (\Throwable $e) {
            echo $e->getMessage();
            // Unrecoverable failure — infrastructure or programming error.
            $workflowRun->markFailed($e->getMessage());

            $this->logger->error('PrintRunWorkflow: fatal failure', [
                'workflow_run_id' => $workflowRun->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $workflowRun;
    }

    // =========================================================================
    // Private — issue resolution
    // =========================================================================

    /**
     * Resolve IssueDeliveries from explicit IDs in the input, or fall back to
     * the process config's eligibility query (active + not yet fulfilled).
     */
    private function resolveIssueDeliveries(
        PrintRunWorkflowInput $input,
        mixed                 $config,
    ): iterable
    {
        if (!empty($input->issueDeliveryIds)) {
            return $this->issueDeliveryRepository->findMany($input->issueDeliveryIds);
        }

        return $this->issueDeliveryRepository->findEligibleForPrintRun($config->site_id);
    }

    // =========================================================================
    // Private — cancellation
    // =========================================================================

    private function cancelPrecedingPrintRuns(iterable $issueDeliveries): void
    {
        foreach ($issueDeliveries as $issueDelivery) {
            $cancelled = $this->printRunRepository->cancelAllPendingForIssueDelivery($issueDelivery->id);

            if ($cancelled > 0) {
                $this->logger->info('PrintRunWorkflow: cancelled preceding pending print runs', [
                    'issue_delivery_id' => $issueDelivery->id,
                    'cancelled_count' => $cancelled,
                ]);
            }
        }
    }

    // =========================================================================
    // Private — PrintRun + batch creation
    // =========================================================================

    /**
     * Creates one PrintRun (pending) and its underlying PrintBatch rows,
     * all within a single transaction.
     *
     * BatchBuilderService owns the grouping logic (regional → one batch per
     * territory, non-regional → one global batch). We only own the PrintRun
     * wrapper and the transaction boundary.
     */
    private function createPrintRunWithBatches(
        IssueDelivery $issueDelivery,
        WorkflowRun   $workflowRun,
        bool          $isRegional,
        bool          $driverSyncEnabled,
        bool          $dryRun,
    ): PrintRun
    {
        if ($dryRun) {
            // Return an unsaved stub so the rest of the loop can count results.
            return new PrintRun([
                'issue_delivery_id' => $issueDelivery->id,
                'workflow_run_id' => $workflowRun->id,
                'status' => PrintRunStatus::PENDING->value,
                'is_regional' => $isRegional,
                'driver_sync_enabled' => $driverSyncEnabled,
            ]);
        }

        return $this->database->transaction(function () use (
            $issueDelivery, $workflowRun, $isRegional, $driverSyncEnabled
        ): PrintRun {
            $printRun = $this->printRunRepository->create([
                'issue_delivery_id' => $issueDelivery->id,
                'workflow_run_id' => $workflowRun->id,
                'status' => PrintRunStatus::PENDING->value,
                'is_regional' => $isRegional,
                'driver_sync_enabled' => $driverSyncEnabled,
            ]);

            // BatchBuilderService already handles regional vs non-regional
            // path selection using IssueDelivery.territory_id and
            // IssueDeliveryRegion rows. We pass $isRegional as contextual
            // metadata on the PrintRun; the builder reads the delivery itself.
            $this->batchBuilderService->buildBatches($issueDelivery);

            // Associate all newly-created batches with this PrintRun.
            $this->batchRepository->attachToPrintRun($printRun);

            return $printRun;
        });
    }

    // =========================================================================
    // Private — driver sync
    // =========================================================================

    /**
     * Push each PrintRun to the external driver. Non-fatal per-run: a sync
     * failure is logged but does not roll back the PrintRun or the WorkflowRun.
     *
     * @param PrintRun[] $printRuns
     */
    private function syncPrintRunsToDriver(
        array                                                               $printRuns,
        \App\Services\Subscriptions\Printing\Driver\PrintRunDriverInterface $driver,
        WorkflowRun                                                         $workflowRun,
    ): void
    {
        foreach ($printRuns as $printRun) {
            try {
                $ref = $driver->sync($printRun);
                $printRun->recordDriverSync($ref);

                $this->logger->info('PrintRunWorkflow: driver sync succeeded', [
                    'workflow_run_id' => $workflowRun->id,
                    'print_run_id' => $printRun->id,
                    'driver_ref' => $ref,
                ]);
            } catch (\RuntimeException $e) {
                // Non-critical: the PrintRun data is already persisted.
                // Sync can be retried manually or via a separate job.
                $this->logger->error('PrintRunWorkflow: driver sync failed', [
                    'workflow_run_id' => $workflowRun->id,
                    'print_run_id' => $printRun->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
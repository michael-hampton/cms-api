<?php

namespace App\DTO\Subscriptions;

/**
 * Trigger input for PrintRunWorkflow.
 *
 * Mirrors the trigger-input pattern used by other fulfilment workflows
 * (e.g. FulfilmentSyncInput). All fields are set at dispatch time and
 * are immutable once the workflow starts.
 *
 * process_config_id  — identifies the PrintProcessConfig that controls
 *                      driver selection, regional mode, and driver_sync flag.
 * issue_delivery_ids — explicit set of IssueDelivery IDs to process.
 *                      When empty the workflow resolves eligible deliveries
 *                      from the process config itself (e.g. active + unfulfilled).
 * force_regional     — override: treat all issues as regional regardless of config.
 * dry_run            — resolve and plan but do not write PrintRun rows.
 */
final class PrintRunWorkflowInput
{
    public function __construct(
        public readonly int   $processConfigId,
        public readonly array $issueDeliveryIds = [],
        public readonly bool  $forceRegional = false,
        public readonly bool  $dryRun = false,
    )
    {
    }

    public function toArray(): array
    {
        return [
            'process_config_id' => $this->processConfigId,
            'issue_delivery_ids' => $this->issueDeliveryIds,
            'force_regional' => $this->forceRegional,
            'dry_run' => $this->dryRun,
        ];
    }
}
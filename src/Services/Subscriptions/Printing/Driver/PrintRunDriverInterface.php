<?php

namespace App\Services\Subscriptions\Printing\Driver;

use App\Models\IssueDelivery;
use App\Models\PrintRun;

/**
 * Contract for print fulfilment driver integrations.
 *
 * A driver has two responsibilities:
 *
 *   1. resolve() — decides whether a given IssueDelivery should be processed
 *      regionally (one PrintBatch per territory) or non-regionally (one global
 *      PrintBatch). This is a query-only operation: no writes, no side effects.
 *
 *   2. sync() — pushes a completed PrintRun to the external supplier system
 *      and returns a supplier-side reference string. Called only when
 *      driver_sync_enabled is true on the PrintProcessConfig.
 *
 * Each concrete driver corresponds to a specific print supplier (e.g. CDS,
 * Marketforce) and is registered by name in PrintDriverRegistry. The name
 * matches the `driver` field on PrintProcessConfig.
 */
interface PrintRunDriverInterface
{
    /**
     * Unique driver identifier. Must match the `driver` field on PrintProcessConfig.
     */
    public function name(): string;

    /**
     * Returns true when this IssueDelivery should produce one PrintBatch per
     * territory (regional), false for a single global PrintBatch.
     *
     * Implementations may inspect the delivery's IssueDeliveryRegion rows,
     * the process config, or a remote API — but must not write anything.
     */
    public function isRegional(IssueDelivery $issueDelivery): bool;

    /**
     * Push a completed PrintRun to the external supplier system.
     *
     * @return string Supplier-side reference (job ID, batch ref, etc.)
     *
     * @throws \RuntimeException On supplier API failure. The workflow catches
     *                           this and marks the WorkflowRun failed.
     */
    public function sync(PrintRun $printRun): string;
}
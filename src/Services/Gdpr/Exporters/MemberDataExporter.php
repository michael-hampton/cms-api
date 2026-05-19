<?php

namespace App\Services\Gdpr\Exporters;

use App\Models\Member;

/**
 * Contract that every domain-specific SAR exporter must implement.
 *
 * Implementing this interface and registering the class with the
 * 'member.exporters' service tag is all that is required to add a
 * new module's data to the SAR bundle — zero changes to core SAR logic.
 */
interface MemberDataExporter
{
    /**
     * Unique key identifying this module in the SAR bundle.
     * e.g. 'orders', 'payments', 'subscriptions'
     */
    public function key(): string;

    /**
     * Return all data belonging to this member within this module.
     * The array must be JSON-serialisable.
     */
    public function export(Member $member): array;
}
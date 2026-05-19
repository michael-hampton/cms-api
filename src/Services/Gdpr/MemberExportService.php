<?php

namespace App\Services\Gdpr;

use App\Models\Member;
use App\Services\Gdpr\Exporters\MemberDataExporter;

/**
 * Aggregates all registered module exporters into a single SAR bundle.
 *
 * Exporters are registered via the service container (tagged with
 * 'member.exporters') so new modules require zero changes here.
 * Export order is deterministic — exporters run in registration order.
 */
final class MemberExportService
{
    /** @param MemberDataExporter[] $exporters */
    public function __construct(
        private readonly array $exporters,
    ) {}

    /**
     * Build the complete SAR bundle for a member.
     * Each exporter contributes its own keyed section.
     * Failures in one module are isolated — other modules still export.
     *
     * @return array{
     *   exported_at: string,
     *   member_id: int,
     *   modules: array<string, array|array{error: string}>
     * }
     */
    public function export(Member $member): array
    {
        $bundle = [
            'exported_at' => date('Y-m-d H:i:s'),
            'member_id'   => $member->id,
            'modules'     => [],
        ];

        foreach ($this->exporters as $exporter) {
            $key = $exporter->key();

            try {
                $bundle['modules'][$key] = $exporter->export($member);
            } catch (\Throwable $e) {
                // Isolate module failure — partial export is better than none
                error_log("[MemberExportService] Exporter [{$key}] failed: " . $e->getMessage());
                $bundle['modules'][$key] = ['error' => 'Export failed for this module.'];
            }
        }

        return $bundle;
    }
}
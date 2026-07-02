<?php

namespace App\Services\PublicContent\Diagnostics;

use App\Services\PublicContent\Parity\PublicContentParityReportWriter;

final class PublicContentDiagnosticsDashboardViewModel
{
    private const DEFAULT_LIMIT = 200;

    public function __construct(
        private readonly JsonLinesFileReader $reader,
        private readonly PublicContentDiagnosticsReportWriter $skipReportWriter,
        private readonly PublicContentParityReportWriter $parityReportWriter,
    ) {
    }

    /** @return array<string, mixed> */
    public function build(int $siteId, string $siteSlug): array
    {
        $skips = $this->forSite(
            $this->reader->tail($this->skipReportWriter->path(), self::DEFAULT_LIMIT),
            $siteId,
        );
        $parityRecords = $this->forSite(
            $this->reader->tail($this->parityReportWriter->path(), self::DEFAULT_LIMIT),
            $siteId,
        );

        return [
            'siteSlug' => $siteSlug,
            'skips' => $skips,
            'skipCountsByWidget' => $this->countBy($skips, 'widget'),
            'skipCountsByReason' => $this->countBy($skips, 'reason'),
            'parityRecords' => $parityRecords,
            'parityMismatches' => array_values(array_filter(
                $parityRecords,
                static fn(array $record): bool => ($record['status'] ?? null) === 'mismatched',
            )),
            'parityFailures' => array_values(array_filter(
                $parityRecords,
                static fn(array $record): bool => ($record['status'] ?? null) === 'failed',
            )),
        ];
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<array<string, mixed>>
     */
    private function forSite(array $records, int $siteId): array
    {
        return array_values(array_filter(
            $records,
            static fn(array $record): bool => (int) ($record['site_id'] ?? 0) === $siteId,
        ));
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return array<string, int>
     */
    private function countBy(array $records, string $field): array
    {
        $counts = [];

        foreach ($records as $record) {
            $key = (string) ($record[$field] ?? 'unknown');
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        arsort($counts);

        return $counts;
    }
}
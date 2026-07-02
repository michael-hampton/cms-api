<?php

namespace App\Services\PublicContent\Diagnostics;

use App\DTO\PublicContent\PublicContentContext;
use App\Enums\PublicContent\WidgetSkipReason;
use App\Framework\Support\Logger;
use Throwable;

final class PublicContentWidgetDiagnostics
{
    /** @var list<array{widget: string, reason: string, page_id: int, site_id: int}> */
    private array $skipped = [];

    public function __construct(
        private readonly PublicContentDiagnosticsReportWriter $reportWriter,
        private readonly Logger $logger,
    ) {
    }

    public function reset(): void
    {
        $this->skipped = [];
    }

    public function recordSkipped(
        string $widgetKey,
        WidgetSkipReason $reason,
        PublicContentContext $context,
    ): void {
        $record = [
            'schema_version' => 1,
            'recorded_at' => date(DATE_ATOM),
            'widget' => $widgetKey,
            'reason' => $reason->value,
            'page_id' => (int) $context->page->id,
            'site_id' => $context->siteId,
        ];

        $this->skipped[] = $record;
        $this->persist($record);
    }

    /** @return list<array{widget: string, reason: string, page_id: int, site_id: int}> */
    public function skipped(): array
    {
        return $this->skipped;
    }

    private function persist(array $record): void
    {
        try {
            $this->reportWriter->append($record);
        } catch (Throwable $exception) {
            $this->logger->warning('Public content widget skip diagnostics could not be written.', [
                'path' => $this->reportWriter->path(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'record' => $record,
            ]);
        }
    }
}
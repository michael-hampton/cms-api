<?php

namespace App\Services\PublicContent\Composition;

use App\DTO\PublicContent\PublicContentContext;

final class PublicContentWidgetDiagnostics
{
    /** @var list<array{widget: string, reason: string, page_id: int, site_id: int}> */
    private array $skipped = [];

    public function reset(): void
    {
        $this->skipped = [];
    }

    public function recordSkipped(
        string $widgetKey,
        string $reason,
        PublicContentContext $context,
    ): void {
        $this->skipped[] = [
            'widget' => $widgetKey,
            'reason' => $reason,
            'page_id' => (int) $context->page->id,
            'site_id' => $context->siteId,
        ];
    }

    /** @return list<array{widget: string, reason: string, page_id: int, site_id: int}> */
    public function skipped(): array
    {
        return $this->skipped;
    }
}

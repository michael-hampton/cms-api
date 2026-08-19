<?php

namespace App\Repositories\PublicContent;

use App\DTO\PublicContent\Widgets\WidgetLayoutOverride;
use App\Enums\PublicContent\WidgetRegion;
use App\Models\Page;
use App\Models\PageWidget;
use App\Repositories\PublicContent\Contracts\PageWidgetRepositoryInterface;
use App\Repositories\Repository;

final class PageWidgetRepository extends Repository implements PageWidgetRepositoryInterface
{
    /** @return list<WidgetLayoutOverride> */
    public function getForPage(int $siteId, int $pageId): array
    {
        if (!$this->pageBelongsToSite($siteId, $pageId)) {
            return [];
        }

        $records = PageWidget::where('page_id', $pageId)
            ->orderBy('region', 'asc')
            ->orderBy('priority', 'asc')
            ->get();

        $overrides = [];
        foreach ($records as $record) {
            $overrides[] = $this->toOverride($record);
        }

        return $overrides;
    }

    public function deleteForPage(int $siteId, int $pageId): void
    {
        if (!$this->pageBelongsToSite($siteId, $pageId)) {
            return;
        }

        PageWidget::where('page_id', $pageId)->delete();
    }

    public function upsert(int $siteId, int $pageId, WidgetLayoutOverride $override): void
    {
        if (!$this->pageBelongsToSite($siteId, $pageId)) {
            return;
        }

        $payload = [
            'region' => ($override->region ?? WidgetRegion::AfterContent)->value,
            'priority' => $override->priority ?? 100,
            'is_enabled' => $override->enabled ?? true,
            'configuration' => $override->configuration,
        ];

        $existing = PageWidget::where('page_id', $pageId)
            ->where('widget_key', $override->widgetKey)
            ->first();

        if ($existing instanceof PageWidget) {
            $existing->fill($payload);
            $existing->save();

            return;
        }

        PageWidget::create([
            'page_id' => $pageId,
            'widget_key' => $override->widgetKey,
            ...$payload,
        ]);
    }

    protected function getModelClass(): string
    {
        return PageWidget::class;
    }

    private function pageBelongsToSite(int $siteId, int $pageId): bool
    {
        $page = Page::where('id', $pageId)->where('site_id', $siteId)->first();

        return $page instanceof Page;
    }

    private function toOverride(PageWidget $record): WidgetLayoutOverride
    {
        $configuration = $record->configuration;
        if (!is_array($configuration)) {
            $configuration = [];
        }

        return new WidgetLayoutOverride(
            widgetKey: (string) $record->widget_key,
            region: WidgetRegion::tryFromConfig($record->region)?->layoutSlot(),
            priority: $record->priority !== null ? (int) $record->priority : null,
            enabled: $record->is_enabled !== null ? (bool) $record->is_enabled : null,
            configuration: $configuration,
        );
    }
}

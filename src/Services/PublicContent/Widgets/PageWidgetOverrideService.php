<?php

namespace App\Services\PublicContent\Widgets;

use App\DTO\PublicContent\Widgets\PublicContentPagePickerItem;
use App\DTO\PublicContent\Widgets\WidgetLayoutOverride;
use App\Framework\Database\Database;
use App\Repositories\PublicContent\Contracts\PageWidgetRepositoryInterface;
use App\Repositories\PublicContent\PublicContentPageRepository;
use InvalidArgumentException;

/**
 * Saves per-page widget layout overrides that beat article-type site config.
 */
final class PageWidgetOverrideService
{
    public function __construct(
        private readonly PageWidgetRepositoryInterface $pageWidgets,
        private readonly PublicContentPageRepository $pages,
        private readonly PublicContentWidgetRegistry $registry,
        private readonly WidgetRegionNormaliser $regions,
        private readonly Database $database,
    ) {
    }

    /**
     * @return list<WidgetLayoutOverride>
     */
    public function listForPage(int $siteId, int $pageId): array
    {
        $this->requirePage($siteId, $pageId);

        return $this->pageWidgets->getForPage($siteId, $pageId);
    }

    /**
     * @return list<PublicContentPagePickerItem>
     */
    public function searchPages(int $siteId, string $query, int $limit = 20): array
    {
        $pages = [];

        foreach ($this->pages->searchForEditor($siteId, $query, $limit) as $page) {
            $pages[] = new PublicContentPagePickerItem(
                id: (int) $page->id,
                title: (string) $page->title,
                slug: (string) $page->slug,
                pageType: (string) $page->page_type,
                status: (string) ($page->status ?? ''),
            );
        }

        return $pages;
    }

    /**
     * @param list<array<string, mixed>> $payload
     * @return list<WidgetLayoutOverride>
     */
    public function syncForPage(int $siteId, int $pageId, array $payload): array
    {
        $this->requirePage($siteId, $pageId);
        $overrides = $this->validatedOverrides($payload);

        return $this->database->transaction(function () use ($siteId, $pageId, $overrides): array {
            $this->pageWidgets->deleteForPage($siteId, $pageId);

            foreach ($overrides as $override) {
                $this->pageWidgets->upsert($siteId, $pageId, $override);
            }

            return $this->pageWidgets->getForPage($siteId, $pageId);
        });
    }

    /**
     * @param list<array<string, mixed>> $payload
     * @return list<WidgetLayoutOverride>
     */
    private function validatedOverrides(array $payload): array
    {
        $overrides = [];

        foreach ($payload as $index => $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("Widget override at index {$index} must be an object.");
            }

            $widgetKey = trim((string) ($row['widget_key'] ?? ''));
            if ($widgetKey === '' || !$this->registry->has($widgetKey)) {
                throw new InvalidArgumentException("Unknown widget key at index {$index}.");
            }

            $region = $this->regions->tryLayoutSlot($row['region'] ?? null);
            if (($row['region'] ?? null) !== null && ($row['region'] ?? '') !== '' && $region === null) {
                throw new InvalidArgumentException("Unknown widget region at index {$index}.");
            }

            $priority = $row['priority'] ?? null;
            if ($priority !== null && !is_numeric($priority)) {
                throw new InvalidArgumentException("Widget priority at index {$index} must be numeric.");
            }

            $configuration = $row['configuration'] ?? [];
            if (!is_array($configuration)) {
                throw new InvalidArgumentException("Widget configuration at index {$index} must be an object.");
            }

            $overrides[] = new WidgetLayoutOverride(
                widgetKey: $widgetKey,
                region: $region,
                priority: $priority !== null ? (int) $priority : null,
                enabled: array_key_exists('is_enabled', $row) ? (bool) $row['is_enabled'] : true,
                configuration: $configuration,
            );
        }

        return $overrides;
    }

    private function requirePage(int $siteId, int $pageId): void
    {
        if ($this->pages->findPreviewById($pageId, $siteId) === null) {
            throw new InvalidArgumentException('Content not found.');
        }
    }
}

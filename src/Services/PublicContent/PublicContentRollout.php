<?php

namespace App\Services\PublicContent;

use App\Enums\PublicContent\PublicPageType;
use App\Models\Page;
use App\Services\PublicContent\Rollout\PublicContentKillSwitch;

class PublicContentRollout
{
    public function __construct(
        private readonly ?PublicContentKillSwitch $killSwitch = null,
    ) {
    }

    public function previewEnabled(): bool
    {
        return (bool) env('PUBLIC_CONTENT_V2_PREVIEW_ENABLED', true);
    }

    public function enabledFor(Page $page): bool
    {
        if (!(bool) env('PUBLIC_CONTENT_V2_ENABLED', false)) {
            return false;
        }

        $siteId = (int) $page->site_id;

        if ($this->killSwitch?->isSiteExcluded($siteId)) {
            return false;
        }

        $siteIds = $this->csvIntegers((string) env('PUBLIC_CONTENT_V2_SITE_IDS', ''));
        if ($siteIds !== [] && !in_array($siteId, $siteIds, true)) {
            return false;
        }

        $pageTypes = $this->csvStrings((string) env(
            'PUBLIC_CONTENT_V2_PAGE_TYPES',
            implode(',', PublicPageType::values()),
        ));

        return $pageTypes === [] || in_array((string) $page->page_type, $pageTypes, true);
    }

    public function shadowEnabled(): bool
    {
        return (bool) env('PUBLIC_CONTENT_V2_SHADOW_ENABLED', false);
    }

    private function csvIntegers(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn(string $item): int => (int) trim($item),
            explode(',', $value),
        )));
    }

    private function csvStrings(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn(string $item): string => trim($item),
            explode(',', $value),
        )));
    }
}

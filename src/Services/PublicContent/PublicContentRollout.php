<?php

namespace App\Services\PublicContent;

use App\Models\Page;

class PublicContentRollout
{
    public function previewEnabled(): bool
    {
        return (bool)env('PUBLIC_CONTENT_V2_PREVIEW_ENABLED', true);
    }

    public function enabledFor(Page $page): bool
    {
        if (!(bool)env('PUBLIC_CONTENT_V2_ENABLED', false)) {
            return false;
        }

        $siteIds = $this->csvIntegers((string)env('PUBLIC_CONTENT_V2_SITE_IDS', ''));
        if ($siteIds !== [] && !in_array((int)$page->site_id, $siteIds, true)) {
            return false;
        }

        $pageTypes = $this->csvStrings((string)env(
            'PUBLIC_CONTENT_V2_PAGE_TYPES',
            'page,content,article,landing-page',
        ));

        return $pageTypes === [] || in_array((string)$page->page_type, $pageTypes, true);
    }

    public function shadowEnabled(): bool
    {
        return (bool)env('PUBLIC_CONTENT_V2_SHADOW_ENABLED', false);
    }

    private function csvIntegers(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn(string $item): int => (int)trim($item),
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

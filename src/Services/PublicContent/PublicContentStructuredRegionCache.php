<?php

namespace App\Services\PublicContent;

use App\Framework\Support\Cache\Cache;
use App\Models\Page;

final class PublicContentStructuredRegionCache
{
    private const int TTL_SECONDS = 300;

    /**
     * @param callable(): array{main: list<array>, sidebar: list<array>} $build
     * @return array{main: list<array>, sidebar: list<array>}
     */
    public function remember(Page $page, callable $build): array
    {
        return Cache::remember(
            $this->key($page),
            self::TTL_SECONDS,
            $build,
        );
    }

    private function key(Page $page): string
    {
        $version = $page->updated_at
            ? strtotime((string) $page->updated_at)
            : 0;

        return sprintf(
            'public-content:structured-regions:%d:%d',
            (int) $page->id,
            $version ?: 0,
        );
    }
}

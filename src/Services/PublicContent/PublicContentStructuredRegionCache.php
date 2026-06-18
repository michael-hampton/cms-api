<?php

namespace App\Services\PublicContent;

use App\Framework\Support\Cache\Cache;
use App\Models\Page;
use App\Parsers\BlockFactory;
use App\Repositories\Cms\BlockRepository;
use Throwable;

final class PublicContentStructuredRegionCache
{
    private const int TTL_SECONDS = 300;

    public function __construct(
        private readonly BlockRepository $blocks,
        private readonly BlockFactory $blockFactory,
    ) {
    }

    /**
     * @return array{main: list<array>, sidebar: list<array>}
     */
    public function for(Page $page): array
    {
        return Cache::remember(
            $this->key($page),
            self::TTL_SECONDS,
            fn (): array => $this->build($page),
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

    /**
     * @return array{main: list<array>, sidebar: list<array>}
     */
    private function build(Page $page): array
    {
        $regions = [
            'main' => [],
            'sidebar' => [],
        ];

        foreach ($this->blocks->getPageBlocks((int) $page->id) as $block) {
            $raw = is_array($block->data)
                ? $block->data
                : (json_decode((string) $block->data, true) ?: []);

            $region = ($raw['context'] ?? 'default') === 'sidebar'
                ? 'sidebar'
                : 'main';
            $input = array_merge($raw, ['type' => $block->type]);

            try {
                $structured = $this->blockFactory->make($input)->toArray();
            } catch (Throwable) {
                $structured = $raw;
            }

            $regions[$region][] = [
                'id' => (int) $block->id,
                'type' => (string) $block->type,
                'order' => (int) $block->order,
                'data' => $structured,
            ];
        }

        return $regions;
    }
}

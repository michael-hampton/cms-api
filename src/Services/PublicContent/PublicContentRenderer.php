<?php

namespace App\Services\PublicContent;

use App\DTO\PublicContent\ContentRegion;
use App\Models\Member;
use App\Models\Page;
use App\Parsers\BlockFactory;
use App\Repositories\Cms\BlockRepository;
use App\Services\Cms\Pages\PageRenderService;
use Throwable;

final class PublicContentRenderer
{
    public function __construct(
        private readonly BlockRepository $blocks,
        private readonly BlockFactory $blockFactory,
        private readonly PageRenderService $pageRenderer,
    ) {
    }

    /**
     * Structured block data is canonical. Region rendered_html is produced by the
     * existing backend page renderer so adverts, grids and zones retain parity.
     *
     * @return array<string, ContentRegion>
     */
    public function render(Page $page, int $siteId, ?Member $member = null): array
    {
        $structuredRegions = [
            'main' => [],
            'sidebar' => [],
        ];

        foreach ($this->blocks->getPageBlocks($page->id) as $block) {
            $raw = is_array($block->data)
                ? $block->data
                : (json_decode((string)$block->data, true) ?: []);

            $region = ($raw['context'] ?? 'default') === 'sidebar' ? 'sidebar' : 'main';
            $input = array_merge($raw, ['type' => $block->type]);

            try {
                $structured = $this->blockFactory->make($input)->toArray();
            } catch (Throwable) {
                $structured = $raw;
            }

            $structuredRegions[$region][] = [
                'id' => (int)$block->id,
                'type' => (string)$block->type,
                'order' => (int)$block->order,
                'data' => $structured,
            ];
        }

        $rendered = $this->pageRenderer->renderPage($page, $siteId, $member);

        return [
            'main' => new ContentRegion(
                'main',
                $structuredRegions['main'],
                (string)($rendered['main'] ?? ''),
            ),
            'sidebar' => new ContentRegion(
                'sidebar',
                $structuredRegions['sidebar'],
                (string)($rendered['sidebar'] ?? ''),
            ),
        ];
    }
}

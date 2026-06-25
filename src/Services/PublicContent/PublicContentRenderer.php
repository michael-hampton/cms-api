<?php

namespace App\Services\PublicContent;

use App\DTO\PublicContent\ContentRegion;
use App\Framework\Support\SiteContext;
use App\Models\Member;
use App\Models\Page;
use App\Parsers\BlockFactory;
use App\Repositories\Cms\BlockRepository;
use App\Services\Cms\Pages\PageRenderService;
use App\Services\PublicContent\Images\PublicContentImageUrlTransformer;
use Throwable;

class PublicContentRenderer
{
    public function __construct(
        private readonly BlockRepository $blocks,
        private readonly BlockFactory $blockFactory,
        private readonly PublicContentStructuredRegionCache $structuredRegionCache,
        private readonly PageRenderService $pageRenderer,
        private readonly PublicContentImageUrlTransformer $imageUrls,
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
        $structuredRegions = $this->structuredRegionCache->remember(
            $page,
            fn (): array => $this->buildStructuredRegions($page),
        );
        $rendered = $this->pageRenderer->renderPage($page, $siteId, $member);
        $siteKey = SiteContext::slug() ?: (string) $siteId;

        return [
            'main' => new ContentRegion(
                'main',
                $this->imageUrls->transformBlocks($structuredRegions['main'], $siteKey),
                $this->imageUrls->transformHtml((string) ($rendered['main'] ?? ''), $siteKey),
            ),
            'sidebar' => new ContentRegion(
                'sidebar',
                $this->imageUrls->transformBlocks($structuredRegions['sidebar'], $siteKey),
                $this->imageUrls->transformHtml((string) ($rendered['sidebar'] ?? ''), $siteKey),
            ),
        ];
    }

    /**
     * @return array{main: list<array>, sidebar: list<array>}
     */
    private function buildStructuredRegions(Page $page): array
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

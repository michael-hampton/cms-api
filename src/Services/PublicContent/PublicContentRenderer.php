<?php

namespace App\Services\PublicContent;

use App\DTO\PublicContent\ContentRegion;
use App\Framework\Support\SiteContext;
use App\Models\Member;
use App\Models\Page;
use App\Parsers\BlockFactory;
use App\Repositories\Cms\BlockRepository;
use App\Services\Cms\Pages\PageRenderService;
use App\Services\PublicContent\Media\PublicContentMediaUrlTransformer;
use Throwable;

final class PublicContentRenderer
{
    public function __construct(
        private readonly BlockRepository $blocks,
        private readonly BlockFactory $blockFactory,
        private readonly PublicContentStructuredRegionCache $structuredRegionCache,
        private readonly PageRenderService $pageRenderer,
        private readonly PublicContentMediaUrlTransformer $mediaUrls,
    ) {
    }

    /**
     * Structured block data is canonical. Region rendered_html is produced by the
     * existing backend page renderer so adverts, grids and zones retain parity.
     *
     * Public API consumers must never receive direct local /storage or /uploads
     * image paths. Admin and persisted block data remain unchanged; only this
     * public delivery boundary gets signed, cacheable media URLs.
     *
     * @return array<string, ContentRegion>
     */
    public function render(Page $page, int $siteId, ?Member $member = null): array
    {
        $siteSlug = SiteContext::slug();
        $structuredRegions = $this->structuredRegionCache->remember(
            $page,
            fn (): array => $this->buildStructuredRegions($page),
        );
        $rendered = $this->pageRenderer->renderPage($page, $siteId, $member);

        return [
            'main' => new ContentRegion(
                'main',
                $this->transformBlocks($structuredRegions['main'], $siteSlug),
                $this->mediaUrls->transformHtml((string) ($rendered['main'] ?? ''), $siteSlug),
            ),
            'sidebar' => new ContentRegion(
                'sidebar',
                $this->transformBlocks($structuredRegions['sidebar'], $siteSlug),
                $this->mediaUrls->transformHtml((string) ($rendered['sidebar'] ?? ''), $siteSlug),
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

    /**
     * @param list<array> $blocks
     * @return list<array>
     */
    private function transformBlocks(array $blocks, string $siteSlug): array
    {
        foreach ($blocks as &$block) {
            if (isset($block['data']) && is_array($block['data'])) {
                $block['data'] = $this->mediaUrls->transformStructuredData($block['data'], $siteSlug);
            }
        }

        return $blocks;
    }
}

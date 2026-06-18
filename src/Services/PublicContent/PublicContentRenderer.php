<?php

namespace App\Services\PublicContent;

use App\DTO\PublicContent\ContentRegion;
use App\Models\Member;
use App\Models\Page;
use App\Services\Cms\Pages\PageRenderService;

final class PublicContentRenderer
{
    public function __construct(
        private readonly PublicContentStructuredRegionCache $structuredRegions,
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
        $structuredRegions = $this->structuredRegions->for($page);
        $rendered = $this->pageRenderer->renderPage($page, $siteId, $member);

        return [
            'main' => new ContentRegion(
                'main',
                $structuredRegions['main'],
                (string) ($rendered['main'] ?? ''),
            ),
            'sidebar' => new ContentRegion(
                'sidebar',
                $structuredRegions['sidebar'],
                (string) ($rendered['sidebar'] ?? ''),
            ),
        ];
    }
}

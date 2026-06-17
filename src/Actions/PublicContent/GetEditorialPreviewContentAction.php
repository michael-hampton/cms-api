<?php

namespace App\Actions\PublicContent;

use App\DTO\PublicContent\PublicContentContext;
use App\DTO\PublicContent\PublicContentDocument;
use App\Framework\Support\SiteContext;
use App\Models\Page;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Services\PublicContent\Composition\PublicContentComposer;
use App\Services\PublicContent\Composition\PublicContentCompositionData;
use App\Services\PublicContent\Composition\PublicContentWidgetDiagnostics;
use App\Services\PublicContent\PublicContentRenderer;
use RuntimeException;

final class GetEditorialPreviewContentAction
{
    public function __construct(
        private readonly PublicContentPageRepository $pages,
        private readonly PublicContentRenderer $renderer,
        private readonly PublicContentCompositionData $compositionData,
        private readonly PublicContentComposer $composer,
        private readonly PublicContentWidgetDiagnostics $widgetDiagnostics,
    ) {
    }

    public function execute(int $siteId, int $pageId): ?PublicContentDocument
    {
        $page = $this->pages->findCompletePreviewById($pageId, $siteId);
        if (!$page) {
            return null;
        }

        if ((int) $page->site_id !== $siteId) {
            throw new RuntimeException('Editorial preview site scope mismatch.');
        }

        $siteSlug = SiteContext::slug();
        $links = [
            'viewer_state' => null,
            'comments' => null,
            'like' => null,
            'view' => null,
            'canonical' => sprintf('/%s/%s', rawurlencode($siteSlug), rawurlencode((string) $page->slug)),
        ];
        $access = ['can_view' => true, 'reason' => null];

        $viewData = $this->compositionData->build(
            page: $page,
            siteId: $siteId,
            siteSlug: $siteSlug,
            member: null,
            links: $links,
            territory: null,
        );
        $viewData['access'] = $access;
        $viewData['editorial_preview'] = true;

        $components = $this->composer->compose(new PublicContentContext(
            page: $page,
            siteId: $siteId,
            siteSlug: $siteSlug,
            member: null,
            viewData: $viewData,
        ));

        return new PublicContentDocument(
            id: (int) $page->id,
            siteId: (int) $page->site_id,
            slug: (string) $page->slug,
            type: (string) $page->page_type,
            title: (string) $page->title,
            summary: $page->meta_description ?: null,
            seo: $page->seo ? $page->seo->toArray() : [],
            taxonomy: $this->taxonomy($page),
            regions: $this->renderer->render($page, $siteId, null),
            components: $components,
            authors: $this->authors($page),
            landingSections: [],
            links: $links,
            widgets: [
                'editorial_preview' => true,
                'status' => (string) $page->status,
                'diagnostics' => [
                    'skipped' => $this->widgetDiagnostics->skipped(),
                ],
            ],
            access: $access,
        );
    }

    private function taxonomy(Page $page): array
    {
        return [
            'categories' => $page->categories?->map(static fn($item) => [
                'id' => (int) $item->id,
                'name' => (string) $item->name,
                'slug' => (string) $item->slug,
            ])->toArray() ?? [],
            'tags' => $page->tags?->map(static fn($item) => [
                'id' => (int) $item->id,
                'name' => (string) $item->name,
                'slug' => (string) $item->slug,
            ])->toArray() ?? [],
        ];
    }

    private function authors(Page $page): array
    {
        return $page->authors?->map(static fn($author) => [
            'id' => (int) $author->id,
            'name' => (string) ($author->name ?? ''),
            'slug' => (string) ($author->slug ?? ''),
            'bio' => $author->bio ?? null,
            'image' => $author->avatar ?? null,
        ])->toArray() ?? [];
    }
}

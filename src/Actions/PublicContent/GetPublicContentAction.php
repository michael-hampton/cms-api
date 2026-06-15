<?php

namespace App\Actions\PublicContent;

use App\DTO\PublicContent\PublicContentContext;
use App\DTO\PublicContent\PublicContentDocument;
use App\Framework\Support\SiteContext;
use App\Models\Member;
use App\Repositories\Cms\Pages\PageRepository;
use App\Services\Cms\Pages\ArticleAccessService;
use App\Services\PublicContent\Composition\PublicContentComposer;
use App\Services\PublicContent\Composition\PublicContentCompositionData;
use App\Services\PublicContent\PublicContentRenderer;

final class GetPublicContentAction
{
    public function __construct(
        private readonly PageRepository $pages,
        private readonly ArticleAccessService $access,
        private readonly PublicContentRenderer $renderer,
        private readonly PublicContentCompositionData $compositionData,
        private readonly PublicContentComposer $composer,
    ) {
    }

    public function execute(int $siteId, string $slug, ?Member $member = null): ?PublicContentDocument
    {
        $page = $this->pages->findBySlug($slug, $siteId);

        if (!$page || (string)$page->status !== 'published') {
            return null;
        }

        $decision = $this->access->canView($page, $member);
        if (!($decision['can_view'] ?? false)) {
            throw new PublicContentAccessDenied(
                (string)($decision['reason'] ?? 'Content access denied.'),
            );
        }

        $page = $this->pages->getCompletePageData((int)$page->id) ?? $page;
        $siteSlug = SiteContext::slug();
        $base = '/api/v1/' . rawurlencode($siteSlug) . '/content/' . $page->id;
        $links = [
            'viewer_state' => $base . '/viewer-state',
            'comments' => $base . '/comments',
            'like' => $base . '/like',
            'view' => $base . '/views',
        ];

        $viewData = $this->compositionData->build(
            page: $page,
            siteId: $siteId,
            siteSlug: $siteSlug,
            member: $member,
            links: $links,
        );

        $components = $this->composer->compose(new PublicContentContext(
            page: $page,
            siteId: $siteId,
            siteSlug: $siteSlug,
            member: $member,
            viewData: $viewData,
        ));

        return new PublicContentDocument(
            id: (int)$page->id,
            siteId: (int)$page->site_id,
            slug: (string)$page->slug,
            type: (string)$page->page_type,
            title: (string)$page->title,
            summary: $page->meta_description ?: null,
            seo: $page->seo ? $page->seo->toArray() : [],
            taxonomy: $this->taxonomy($page),
            regions: $this->renderer->render($page, $siteId, $member),
            components: $components,
            authors: $this->authors($page),
            landingSections: [],
            links: $links,
            widgets: [],
        );
    }

    private function taxonomy($page): array
    {
        return [
            'categories' => $page->categories?->map(static fn($item) => [
                'id' => (int)$item->id,
                'name' => (string)$item->name,
                'slug' => (string)$item->slug,
            ])->toArray() ?? [],
            'tags' => $page->tags?->map(static fn($item) => [
                'id' => (int)$item->id,
                'name' => (string)$item->name,
                'slug' => (string)$item->slug,
            ])->toArray() ?? [],
        ];
    }

    private function authors($page): array
    {
        return $page->authors?->map(static fn($author) => [
            'id' => (int)$author->id,
            'name' => (string)($author->name ?? ''),
            'slug' => (string)($author->slug ?? ''),
            'bio' => $author->bio ?? null,
            'image' => $author->avatar ?? null,
        ])->toArray() ?? [];
    }
}

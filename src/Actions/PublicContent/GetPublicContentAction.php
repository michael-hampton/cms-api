<?php

namespace App\Actions\PublicContent;

use App\DTO\PublicContent\PublicContentDocument;
use App\Framework\Support\SiteContext;
use App\Models\Category;
use App\Models\Member;
use App\Repositories\Cms\Pages\PageRepository;
use App\Services\Cms\Pages\ArticleAccessService;
use App\Services\PublicContent\PublicContentRenderer;
use App\Services\PublicContent\PublicContentSupplementaryService;

final class GetPublicContentAction
{
    public function __construct(
        private readonly PageRepository $pages,
        private readonly ArticleAccessService $access,
        private readonly PublicContentRenderer $renderer,
        private readonly PublicContentSupplementaryService $supplementary,
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
            throw new PublicContentAccessDenied((string)($decision['reason'] ?? 'Content access denied.'));
        }

        $page = $this->pages->getCompletePageData((int)$page->id) ?? $page;
        $siteSlug = SiteContext::slug();
        $base = '/api/v1/' . $siteSlug . '/content/' . $page->id;

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
            authors: $this->authors($page),
            landingSections: $this->landingSections($page, $siteId, $siteSlug),
            links: [
                'viewer_state' => $base . '/viewer-state',
                'comments' => $base . '/comments',
                'like' => $base . '/like',
                'view' => $base . '/views',
            ],
            widgets: $this->supplementary->for($page, $siteId, $siteSlug),
        );
    }

    private function taxonomy($page): array
    {
        return [
            'categories' => $page->categories?->map(fn($item) => ['id' => (int)$item->id, 'name' => (string)$item->name, 'slug' => (string)$item->slug])->toArray() ?? [],
            'tags' => $page->tags?->map(fn($item) => ['id' => (int)$item->id, 'name' => (string)$item->name, 'slug' => (string)$item->slug])->toArray() ?? [],
        ];
    }

    private function authors($page): array
    {
        return $page->authors?->map(fn($author) => [
            'id' => (int)$author->id,
            'name' => (string)($author->name ?? ''),
            'slug' => (string)($author->slug ?? ''),
            'bio' => $author->bio ?? null,
            'image' => $author->image ?? $author->image_url ?? null,
        ])->toArray() ?? [];
    }

    private function landingSections($page, int $siteId, string $siteSlug): array
    {
        if ((string)$page->page_type !== 'landing-page') {
            return [];
        }

        $sections = [];
        foreach (Category::where('site_id', $siteId)->orderBy('name')->get() as $category) {
            $items = $this->pages->getPagesByCategory((int)$category->id, 6, $siteId);
            if ($items->count() < 3) {
                continue;
            }
            $sections[] = [
                'category' => ['id' => (int)$category->id, 'name' => $category->name, 'slug' => $category->slug],
                'pages' => $items->map(fn($item) => [
                    'id' => (int)$item->id,
                    'title' => (string)$item->title,
                    'slug' => (string)$item->slug,
                    'url' => '/' . $siteSlug . '/' . $item->slug,
                    'summary' => $item->meta_description ?? null,
                ])->toArray(),
            ];
        }
        return $sections;
    }
}

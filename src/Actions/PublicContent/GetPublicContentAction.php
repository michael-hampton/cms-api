<?php

namespace App\Actions\PublicContent;

use App\DTO\PublicContent\PublicContentDocument;
use App\Models\Member;
use App\Repositories\Cms\Pages\PageRepository;
use App\Services\Cms\Pages\ArticleAccessService;
use App\Services\PublicContent\PublicContentRenderer;

final class GetPublicContentAction
{
    public function __construct(
        private readonly PageRepository $pages,
        private readonly ArticleAccessService $access,
        private readonly PublicContentRenderer $renderer,
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

        return new PublicContentDocument(
            id: (int)$page->id,
            siteId: (int)$page->site_id,
            slug: (string)$page->slug,
            type: (string)$page->page_type,
            title: (string)$page->title,
            summary: $page->meta_description ?: null,
            seo: $page->seo ? $page->seo->toArray() : [],
            taxonomy: [
                'categories' => $page->categories?->map(fn($category) => [
                    'id' => (int)$category->id,
                    'name' => (string)$category->name,
                    'slug' => (string)$category->slug,
                ])->toArray() ?? [],
                'tags' => $page->tags?->map(fn($tag) => [
                    'id' => (int)$tag->id,
                    'name' => (string)$tag->name,
                    'slug' => (string)$tag->slug,
                ])->toArray() ?? [],
            ],
            regions: $this->renderer->render($page, $siteId),
        );
    }
}

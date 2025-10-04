<?php

namespace App\Renderers;

use App\Framework\Http\Request;
use App\Models\Page;
use App\Services\Url\UrlResolutionResult;

class BlogPostRenderer extends BasePageRenderer
{
    public function __construct(
        protected readonly Request $request,
        private readonly BlogService $blogService
    ) {
        parent::__construct($request);
    }

    protected function renderPage(Page $page, UrlResolutionResult $result): mixed
    {
        $data = array_merge($this->getBaseViewData($page, $result), [
            'content' => $page->content,
            'excerpt' => $page->excerpt,
            'author' => $this->blogService->getAuthor($page->author_id),
            'tags' => $this->blogService->getTags($page->id),
            'related_posts' => $this->blogService->getRelatedPosts($page),
            'published_date' => $page->published_at,
        ]);

        return view($page->template ?: 'blog.post', $data);
    }

    protected function buildBreadcrumbs(Page $page): array
    {
        return [
            ['title' => 'Home', 'url' => route('home')],
            ['title' => 'Blog', 'url' => route('blog.index')],
            ['title' => $page->title, 'url' => null]
        ];
    }
}
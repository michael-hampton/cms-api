<?php

namespace App\Renderers;

use App\Framework\Http\Request;
use App\Models\Page;
use App\Services\Url\UrlResolutionResult;

abstract class BasePageRenderer implements PageRendererInterface
{
    public function __construct(
        protected readonly Request $request
    ) {}

    public function render(UrlResolutionResult $result): mixed
    {
        if (!$result->isPage()) {
            throw new \InvalidArgumentException('Result must be a page');
        }

        return $this->renderPage($result->page, $result);
    }

    abstract protected function renderPage(Page $page, UrlResolutionResult $result): mixed;

    protected function getBaseViewData(Page $page, UrlResolutionResult $result): array
    {
        return [
            'page' => $page,
            'title' => $page->title,
            'canonical_url' => $result->canonicalUrl,
            'meta_tags' => $this->buildMetaTags($result),
            'breadcrumbs' => $this->buildBreadcrumbs($page),
        ];
    }

    protected function buildMetaTags(UrlResolutionResult $result): array
    {
        $tags = [];

        if ($result->canonicalUrl) {
            $tags[] = ['rel' => 'canonical', 'href' => $result->canonicalUrl];
        }

        if ($result->meta['description'] ?? null) {
            $tags[] = ['name' => 'description', 'content' => $result->meta['description']];
        }

        if ($result->meta['keywords'] ?? null) {
            $tags[] = ['name' => 'keywords', 'content' => $result->meta['keywords']];
        }

        return $tags;
    }

    protected function buildBreadcrumbs(Page $page): array
    {
        return [
            ['title' => 'Home', 'url' => route('home')],
            ['title' => $page->title, 'url' => null]
        ];
    }
}
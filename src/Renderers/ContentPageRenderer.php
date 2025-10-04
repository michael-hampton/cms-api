<?php

namespace App\Renderers;

use App\Models\Page;
use App\Services\Url\UrlResolutionResult;

class ContentPageRenderer extends BasePageRenderer
{
    protected function renderPage(Page $page, UrlResolutionResult $result): mixed
    {
        $data = array_merge($this->getBaseViewData($page, $result), [
            'content' => $page->content,
            'layout' => $page->layout ?? 'default'
        ]);

        return view($page->template ?: 'pages.content', $data);
    }
}
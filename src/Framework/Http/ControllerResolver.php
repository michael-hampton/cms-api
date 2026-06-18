<?php

namespace App\Framework\Http;

use App\Models\Page;

class ControllerResolver implements ControllerResolverInterface
{
    public function __construct(
        private array $controllerMappings = []
    ) {
        $this->controllerMappings = array_merge([
            'page' => 'App\Controllers\Front\ApiFirstPublicContentController@show',
            'article' => 'App\Controllers\Front\ApiFirstPublicContentController@show',
            'content' => 'App\Controllers\Front\ApiFirstPublicContentController@show',
            'landing-page' => 'App\Controllers\Front\ApiFirstPublicContentController@show',
            'buying-guide' => 'App\Controllers\Front\ApiFirstPublicContentController@show',
            'gallery' => 'App\Controllers\Front\ApiFirstPublicContentController@show',
            'review' => 'App\Controllers\Front\ApiFirstPublicContentController@show',
            'blog' => 'App\Controllers\Front\ApiFirstPublicContentController@show',
            'product' => 'App\Controllers\ProductController@show',
            'category' => 'App\Controllers\CategoryController@show',
            'event' => 'App\Controllers\Front\ApiFirstPublicContentController@show',
            'documentation' => 'App\Controllers\DocsController@show',
        ], $this->controllerMappings);
    }

    public function resolve(Page $page): ?string
    {
        if ($page->controller) {
            return $page->controller;
        }

        if ($page->custom_handler) {
            return $page->custom_handler;
        }

        return $this->controllerMappings[$page->page_type] ?? null;
    }

    public function shouldUseController(Page $page): bool
    {
        return $this->resolve($page) !== null;
    }
}

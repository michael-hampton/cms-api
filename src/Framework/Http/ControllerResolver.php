<?php

namespace App\Framework\Http;

use App\Models\Page;

class ControllerResolver implements ControllerResolverInterface
{
    public function __construct(
        private array $controllerMappings = []
    ) {
        $this->controllerMappings = array_merge([
            'page' => 'App\Controllers\Front\ContentController@show',
            'article' => 'App\Controllers\Front\ContentController@show',
            'content' => 'App\Controllers\Front\ContentController@show',
            'landing-page' => 'App\Controllers\Front\ContentController@show',
            'buying-guide' => 'App\Controllers\Front\ContentController@show',
            'gallery' => 'App\Controllers\Front\ContentController@show',
            'review' => 'App\Controllers\Front\ContentController@show',
            'blog' => 'App\Controllers\Front\ContentController@show',
            'product' => 'App\Controllers\ProductController@show',
            'category' => 'App\Controllers\CategoryController@show',
            'event' => 'App\Controllers\Front\ContentController@show',
            'documentation' => 'App\Controllers\DocsController@show',
        ], $this->controllerMappings);
    }

    public function resolve(Page $page): ?string
    {
        if ($page->custom_handler) {
            return $page->custom_handler;
        }

        if (isset($this->controllerMappings[$page->page_type])) {
            return $this->controllerMappings[$page->page_type];
        }

        if ($page->controller) {
            return $page->controller;
        }

        return null;
    }

    public function shouldUseController(Page $page): bool
    {
        return $this->resolve($page) !== null;
    }
}

<?php

namespace App\Framework\Http;

use App\Models\Page;

class ControllerResolver implements ControllerResolverInterface
{
    public function __construct(
        private array $controllerMappings = []
    ) {
        // Default controller mappings
        $this->controllerMappings = array_merge([
            // Page type => Controller@method
            'content' => 'App\Controllers\ContentController@show',
            'landing-page' => 'App\Controllers\ContentController@show',
            'gallery' => 'App\Controllers\ContentController@show',
            'blog' => 'App\Controllers\ContentController@show',
            'product' => 'App\Controllers\ProductController@show',
            'category' => 'App\Controllers\CategoryController@show',
            'event' => 'App\Controllers\ContentController@show',
            'documentation' => 'App\Controllers\DocsController@show',
        ], $this->controllerMappings);
    }

    public function resolve(Page $page): ?string
    {
        // 1. Check for explicit controller in page data
        if ($page->controller) {
            return $page->controller;
        }

        // 2. Check for page type mapping
        if (isset($this->controllerMappings[$page->page_type])) {
            return $this->controllerMappings[$page->page_type];
        }

        // 3. Check for custom handler class
        if ($page->custom_handler) {
            return $page->custom_handler;
        }

        return null;
    }

    public function shouldUseController(Page $page): bool
    {
        return $this->resolve($page) !== null;
    }
}
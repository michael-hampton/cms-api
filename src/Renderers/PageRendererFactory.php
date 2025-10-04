<?php

namespace App\Renderers;

use App\Framework\Http\Request;

class PageRendererFactory
{
    public function __construct(
        private readonly Request $request,
        private readonly BlogService $blogService,
        private readonly ProductService $productService,
        private readonly CategoryService $categoryService
    ) {}

    public function create(string $pageType): PageRendererInterface
    {
        return match ($pageType) {
            'content', 'page' => new ContentPageRenderer($this->request),
            'blog_post', 'article' => new BlogPostRenderer($this->request, $this->blogService),
            'product' => new ProductPageRenderer($this->request, $this->productService),
            'category' => new CategoryPageRenderer($this->request, $this->categoryService),
            'landing' => new LandingPageRenderer($this->request),
            'custom' => new CustomPageRenderer($this->request),
            default => new ContentPageRenderer($this->request)
        };
    }
}
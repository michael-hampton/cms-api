<?php

namespace App\Controllers;

use App\Models\Menu;
use App\Models\Page;
use App\Repositories\CategoryRepository;
use App\Repositories\PageRepository;

class CategoryPageController extends Controller
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private PageRepository $pageRepository
    ) {
        parent::__construct();
    }

    public function show(string $slug)
    {
        $category = $this->categoryRepository->findBySlug($slug);

        if (!$category) {
            return $this->notFound();
        }

        $pages = $this->pageRepository->getPagesByCategory($category->id, 20);
        $menu = Menu::where('is_active', true)->with(['items'])->first();
        $breadcrumb = $category->getBreadcrumb();
        $childCategories = $this->categoryRepository->getChildCategories($category->id);

        // Get current page from query string
        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = 12;

        // Get paginated pages for this category
        $paginationData = Page::whereHas('categories', function($query) use ($category) {
            $query->where('categories.id', $category->id);
        })
            ->where('status', 'Published')
            ->with(['tags'])
            ->orderBy('published_at', 'desc')
            ->paginate($perPage, $currentPage);

        $pages = $paginationData['data'];
        $pagination = [
            'current_page' => $paginationData['pagination']['current_page'],
            'per_page' => $paginationData['pagination']['per_page'],
            'total' => $paginationData['pagination']['total'],
            'last_page' => $paginationData['pagination']['last_page'],
            'from' => $paginationData['pagination']['from'],
            'to' => $paginationData['pagination']['to']
        ];

        return $this->view('estate/category', [
            'category' => $category,
            'pages' => $pages,
            'menu' => $menu,
            'breadcrumb' => $breadcrumb,
            'childCategories' => $childCategories,
            'pagination' => $pagination,
        ]);
    }

    public function index()
    {
        $categories = $this->categoryRepository->getRootCategories();
        $menu = Menu::where('is_active', true)->with(['items'])->first();

        return $this->view('estate/categories', [
            'categories' => $categories,
            'menu' => $menu
        ]);
    }

    private function notFound()
    {
        http_response_code(404);
        return $this->view('estate/404', [
            'menu' => Menu::where('is_active', true)->with(['items'])->first()
        ]);
    }
}
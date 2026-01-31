<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;
use App\Models\Menu;
use App\Models\Page;
use App\Repositories\Cms\CategoryRepository;
use App\Services\Cms\MenuRenderer;
use App\Services\Cms\Pages\ArticleAccessService;
use App\Services\Front\CategoryProductService;
use App\Services\Subscriptions\SubscriptionModalService;

class CategoryPageController extends Controller
{
    public function __construct(
        private readonly CategoryRepository       $categoryRepository,
        private readonly ArticleAccessService     $articleAccessService,
        private readonly SubscriptionModalService $subscriptionModalService,
        private readonly CategoryProductService $categoryProductService,
    ) {
        parent::__construct();
    }

    public function show(string $slug)
    {
        $category = $this->categoryRepository->findBySlug($slug);

        if (!$category) {
            return $this->notFound();
        }

        $menu = Menu::where('is_active', true)
            ->where('site_id', SiteContext::getId())
            ->where('menu_type', 'header')
            ->with(['items'])
            ->first();

        $breadcrumb = $category->getBreadcrumb();
        $childCategories = $this->categoryRepository->getChildCategories($category->id);

        // Get products for this category (limit to 8 for display)
        $products = $this->categoryProductService->getCategoryProducts($category->id, 8);

        $offers = $this->categoryProductService->getCategoryOffers($category->id, 6);

        $stats = $this->categoryProductService->getCategoryStats($category->id);

        $newProducts = $this->categoryProductService->getNewProducts($category->id);

        $featuredProducts = $this->categoryProductService->getFeaturedProducts($category->id);

        $reviews = $this->categoryProductService->getCategoryReviews($category->id);

        // Get current page from query string
        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = 12;

        // Get filter parameters
        $sort = $_GET['sort'] ?? 'latest';
        $authorFilter = $_GET['author'] ?? '';

        // Build query
        $query = Page::whereHas('categories', function ($query) use ($category) {
            $query->where('categories.id', $category->id);
        })
            ->where('status', 'Published')
            ->with(['tags', 'authors', 'categories']);

        // Apply author filter
        if ($authorFilter) {
            $query->whereHas('authors', function ($q) use ($authorFilter) {
                $q->where('authors.id', $authorFilter);
            });
        }

        // Apply sorting
        switch ($sort) {
            case 'oldest':
                $query->orderBy('published_at', 'asc');
                break;
            case 'title':
                $query->orderBy('title', 'asc');
                break;
            case 'latest':
            default:
                $query->orderBy('published_at', 'desc');
                break;
        }

        $paginationData = $query->paginate($perPage, $currentPage);
        $member = MemberAuth::getMember();

        $pages = $paginationData['data'];

        $pages->map(function ($page) use ($member) {
            $accessInfo = $this->articleAccessService->enrichPageWithAccessInfo($page, $member);
            $page->access = $accessInfo;
        });

        $pagination = [
            'current_page' => $paginationData['pagination']['current_page'],
            'per_page' => $paginationData['pagination']['per_page'],
            'total' => $paginationData['pagination']['total'],
            'last_page' => $paginationData['pagination']['last_page'],
            'from' => $paginationData['pagination']['from'],
            'to' => $paginationData['pagination']['to']
        ];

        $siteId = SiteContext::getId();
        $modalData = $this->subscriptionModalService->getModalData($member, $siteId);

        return $this->view('estate/category', [
            'category' => $category,
            'pages' => $pages,
            'menu' => $menu,
            'breadcrumb' => $breadcrumb,
            'products' => $products,
            'offers' => $offers,
            'stats' => $stats,
            'reviews' => $reviews,
            'childCategories' => $childCategories,
            'newProducts' => $newProducts,
            'featuredProducts' => $featuredProducts,
            'pagination' => $pagination,
            'currentSort' => $sort,
            'menuRenderer' => new MenuRenderer(),
            'subscriptionModalData' => $modalData,
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

//    private function notFound()
//    {
//        http_response_code(404);
//        return $this->view('estate/404', [
//            'menu' => Menu::where('is_active', true)->with(['items'])->first()
//        ]);
//    }
}
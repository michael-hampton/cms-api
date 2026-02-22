<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\DTO\Pages\PageFilterDto;
use App\Enums\Pages\PageFilterType;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Menu;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\MenuRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Services\Cms\MenuRenderer;
use App\Services\Cms\Pages\ArticleAccessService;
use App\Services\Front\CategoryProductService;
use App\Services\Subscriptions\SubscriptionModalService;

class CategoryPageController extends Controller
{
    public function __construct(
        private readonly CategoryRepository       $categoryRepository,
        private readonly PageRepository         $pageRepository,
        private readonly MenuRepository         $menuRepository,
        private readonly ArticleAccessService     $articleAccessService,
        private readonly SubscriptionModalService $subscriptionModalService,
        private readonly CategoryProductService $categoryProductService,
    ) {
        parent::__construct();
    }

    public function show(string $slug, Request $request)
    {
        $category = $this->categoryRepository->findBySlug($slug);

        if (!$category) {
            return $this->notFound();
        }

        $breadcrumb = $category->getBreadcrumb();
        $childCategories = $this->categoryRepository->getChildCategories($category->id);

        // Get products for this category (limit to 8 for display)
        $products = $this->categoryProductService->getCategoryProducts($category->id, 8);
        $offers = $this->categoryProductService->getCategoryOffers($category->id, 6);
        $stats = $this->categoryProductService->getCategoryStats($category->id);
        $newProducts = $this->categoryProductService->getNewProducts($category->id);
        $featuredProducts = $this->categoryProductService->getFeaturedProducts($category->id);
        $reviews = $this->categoryProductService->getCategoryReviews($category->id);

        $currentPage = !empty($request->input('page')) ? max(1, (int)$request->input('page')) : 1;
        $perPage = 12;
        $sort = $request->input('sort') ?? 'latest';
        $authorFilter = $request->input('author') ?? '';

        $filter = PageFilterDto::make(
            filterType: PageFilterType::Category,
            filterId: $category->id,
            sort: $sort,
            status: 'Published',
            currentPage: PageFilterDto::sanitisePage($_GET['page'] ?? 1),
            secondary: $authorFilter ? ['author' => $authorFilter] : [],
        );

        $paginationData = $this->pageRepository->getPaginatedPages($filter);

        $member = MemberAuth::getMember();
        $pages = $paginationData['data'];

        $pages->map(function ($page) use ($member) {
            $page->access = $this->articleAccessService->enrichPageWithAccessInfo($page, $member);
        });

        $siteId = SiteContext::getId();
        $modalData = $this->subscriptionModalService->getModalData($member, $siteId);

        return $this->view('estate/category', [
            'category' => $category,
            'pages' => $pages,
            'menu' => $this->menuRepository->findActiveHeaderMenu($siteId),
            'breadcrumb' => $breadcrumb,
            'products' => $products,
            'offers' => $offers,
            'stats' => $stats,
            'reviews' => $reviews,
            'childCategories' => $childCategories,
            'newProducts' => $newProducts,
            'featuredProducts' => $featuredProducts,
            'pagination' => $paginationData['pagination'],
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
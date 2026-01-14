<?php
namespace App\Controllers;

use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Product;
use App\Repositories\Cms\BrandRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Product\ProductSpecificationGroupRepository;
use App\Services\Cms\MenuRenderer;
use App\Services\Product\DealAlertService;
use App\Services\Product\DealsService;
use App\Services\Product\PriceAlertService;

class DealsController extends Controller
{
    public function __construct(
        private readonly DealsService $dealsService,
        private readonly PriceAlertService $priceAlertService,
        private readonly DealAlertService $dealAlertService,
        private readonly CategoryRepository $categoryRepository,
        private readonly BrandRepository $brandRepository,
    ) {
        parent::__construct();
    }

    public function index(Request $request)
    {
        // Get all categories
        $categories = Category::orderBy('name')->get();

        // Get product counts for each category using groupBy
        $categoryProducts = Product::select('category_id')
            ->groupBy('category_id')
            ->get();

        // Count products per category
        $categoryCounts = [];
        foreach ($categoryProducts as $product) {
            $categoryId = $product->category_id;
            if (!isset($categoryCounts[$categoryId])) {
                $categoryCounts[$categoryId] = 0;
            }
            $categoryCounts[$categoryId]++;
        }

        // Alternative approach using raw SQL if the above doesn't work
        // $db = Database::getInstance();
        // $stmt = $db->query('SELECT category_id, COUNT(*) as count FROM products GROUP BY category_id', []);
        // $results = $stmt->fetchAll();
        // $categoryCounts = [];
        // foreach ($results as $row) {
        //     $categoryCounts[$row['category_id']] = $row['count'];
        // }

        // Add counts to categories
        $categories = $categories->map(function ($category) use ($categoryCounts) {
            return (object)[
                'id' => $category->id,
                'name' => $category->name,
                'product_count' => $categoryCounts[$category->id] ?? 0
            ];
        });

        // Get all brands
        $brands = Brand::orderBy('name')->get();

        // Get product counts for each brand
        $brandProducts = Product::select('brand_id')
            ->groupBy('brand_id')
            ->get();

        // Count products per brand
        $brandCounts = [];
        foreach ($brandProducts as $product) {
            $brandId = $product->brand_id;
            if (!isset($brandCounts[$brandId])) {
                $brandCounts[$brandId] = 0;
            }
            $brandCounts[$brandId]++;
        }

        // Add counts to brands
        $brands = $brands->map(function ($brand) use ($brandCounts) {
            return (object)[
                'id' => $brand->id,
                'name' => $brand->name,
                'product_count' => $brandCounts[$brand->id] ?? 0
            ];
        });

        $menu = Menu::where('is_active', true)
            ->where('site_id', SiteContext::getId())
            ->where('menu_type', 'header')
            ->with(['items'])
            ->first();

        $siteId = SiteContext::getId();

        $deals = $this->dealsService->getTodaysDeals();

        $dealsService = new DealsService();

        // Get specification groups with counts
        $specRepository = app(ProductSpecificationGroupRepository::class);
        $specificationGroups = $specRepository->getAllWithCounts($siteId);

        return $this->view('deals.index', [
            'categories' => $categories->toArray(),
            'brands' => $brands->toArray(),
            'menu' => $menu,
            'menuRenderer' => new MenuRenderer(),
            'specificationGroups' => $specificationGroups->toArray(),
            'deals' => $deals,
            'todaysDeals' => $dealsService->getTodaysDeals(10),
        ]);
    }

    public function refresh()
    {
        $deals = $this->dealsService->refreshTodaysDeals();
        return $this->resourceResponse(['deals' => $deals]);
    }

    public function carousel()
    {
        $deals = $this->dealsService->getTodaysDeals(10);
        return $this->resourceResponse(['deals' => $deals]);
    }

    public function filtered(Request $request)
    {
        $filters = $request->all();
        $deals = $this->dealsService->getFilteredDeals($filters);
        return $this->resourceResponse($deals);
    }

    public function createPriceAlert(Request $request)
    {
        $data = $request->all();
        $result = $this->priceAlertService->createAlert($data);
        return $this->resourceResponse($result);
    }

    public function subscribeDealAlert(Request $request)
    {
        $data = $request->all();

        $result = $this->dealAlertService->subscribe($data);
        return $this->resourceResponse($result);
    }

    public function verifyDealAlert(Request $request)
    {
        $token = $request->query('token');
        $result = $this->dealAlertService->verify($token);

        if ($result['success']) {
            return $this->view('deal-alerts/verified', $result);
        }

        return $this->view('deal-alerts/error', $result);
    }

    public function unsubscribeDealAlert(Request $request)
    {
        $email = $request->input('email');
        $result = $this->dealAlertService->unsubscribe($email);
        return $this->resourceResponse($result);
    }
}
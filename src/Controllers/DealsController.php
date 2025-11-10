<?php
namespace App\Controllers;

use App\Framework\Http\Request;
use App\Models\Brand;
use App\Models\Category;
use App\Repositories\BrandRepository;
use App\Repositories\CategoryRepository;
use App\Services\DealsService;
use App\Services\PriceAlertService;

class DealsController extends Controller
{
    public function __construct(
        private readonly DealsService $dealsService,
        private readonly PriceAlertService $priceAlertService,
        private readonly CategoryRepository $categoryRepository,
        private readonly BrandRepository $brandRepository,
    ) {
        parent::__construct();
    }

    public function index()
    {
        $deals = $this->dealsService->getTodaysDeals();

        return $this->view('deals/index', [
            'deals' => $deals,
            'categories' => $this->categoryRepository->getActive()->toArray(),
            'brands' => $this->brandRepository->getActiveBrands()->toArray()
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
}
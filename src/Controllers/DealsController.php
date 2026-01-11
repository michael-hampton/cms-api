<?php
namespace App\Controllers;

use App\Framework\Http\Request;
use App\Repositories\Cms\BrandRepository;
use App\Repositories\Cms\CategoryRepository;
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

    public function index()
    {
        $deals = $this->dealsService->getTodaysDeals();

        $dealsService = new DealsService();

        return $this->view('deals/index', [
            'deals' => $deals,
            'todaysDeals' => $dealsService->getTodaysDeals(10),
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
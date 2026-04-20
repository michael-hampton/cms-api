<?php

declare(strict_types=1);

namespace App\Controllers\MerchantPortal;

use App\Controllers\Controller;
use App\Framework\Http\Response;
use App\Models\Merchant;
use App\Repositories\Adverts\Boost\BoostRepository;
use App\Repositories\Adverts\Boost\MerchantBoostStatRepository;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Billing\ShipmentRepository;
use App\Repositories\Commission\CommissionRepository;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Product\MerchantRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\ReviewRepository;
use App\Repositories\Vouchers\VoucherRepository;
use App\Services\Adverts\MerchantAnalyticsService;
use App\Services\Product\MerchantStatsService;

class MerchantDashboardController extends Controller
{
    public function __construct(
        private readonly MerchantRepository          $merchantRepository,
        private readonly ProductRepository           $productRepository,
        private readonly ProductOfferRepository      $offerRepository,
        private readonly BoostRepository             $boostRepository,
        private readonly MerchantBoostStatRepository $merchantBoostStatRepository,
        private readonly ShipmentRepository          $shipmentRepository,
        private readonly OrderRepository             $orderRepository,
        private readonly VoucherRepository           $voucherRepository,
        private readonly ReviewRepository            $reviewRepository,
        private readonly CommissionRepository        $commissionRepository,
        private readonly MerchantStatsService        $statsService,
        private readonly MerchantAnalyticsService $analyticsService,
    )
    {
        parent::__construct();
    }

    public function index(): Response
    {
        // Resolved via the authenticated guard — no static Merchant::find() call.
        $merchant = Merchant::find(1);

        $stats = $this->statsService->forMerchant($merchant);
        $analytics = $this->analyticsService->forMerchant($merchant, days: 30);

        $boostResult = $this->boostRepository->getAllWithFilters([
            'merchant_id' => $merchant->id,
            'status' => 'active',
            'per_page' => 5,
        ]);

        $activeBoosts = $boostResult['data'];
        $boostStats = $this->merchantBoostStatRepository->findByMerchant($merchant->id);
        $recentOrders = $this->orderRepository->getRecentForMerchant($merchant->id);
        $topProducts = $this->productRepository->topByRevenueForMerchant($merchant->id, limit: 4);
        $products = $this->productRepository->getProductsByMerchant($merchant->id);
        $offers = $this->offerRepository->search(['merchant_id' => $merchant->id]);
        $shipments = $this->shipmentRepository->getByMerchantId($merchant->id);
        $vouchers = $this->voucherRepository->getByMerchant($merchant->id);

        $transactions = $this->merchantRepository->getTransactions($merchant->id, [
            'status' => 'completed',
        ]);

        $commissionSummary = $this->commissionRepository->summaryForMerchant($merchant->id);
        $commissionByProduct = $this->commissionRepository->byProductForMerchant($merchant->id);
        $commissionRates = $this->commissionRepository->ratesByMerchant($merchant->id);

        $recentReviews = $this->reviewRepository->recentForMerchant($merchant->id, limit: 10);
        $reviewStats = $this->reviewRepository->statsForMerchant($merchant->id);

        return $this->view('merchant-portal/dashboard', compact(
            'merchant',
            'stats',
            'analytics',
            'activeBoosts',
            'boostStats',
            'recentOrders',
            'topProducts',
            'products',
            'offers',
            'shipments',
            'vouchers',
            'transactions',
            'commissionSummary',
            'commissionByProduct',
            'commissionRates',
            'recentReviews',
            'reviewStats',
        ));
    }
}
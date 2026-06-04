<?php

namespace App\Controllers\Offers;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Session\Session;
use App\Framework\Support\SiteContext;
use App\Models\Menu;
use App\Repositories\Cms\BrandRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\RegionSetRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductSpecificationGroupRepository;
use App\Repositories\Product\ProductViewRepository;
use App\Repositories\Shopping\CartRepository;
use App\Repositories\Shopping\WishlistRepository;
use App\Services\Cms\MenuRenderer;
use App\Services\Currency\CurrencyResolver;
use App\Services\Offers\DealAlertService;
use App\Services\Offers\DealsService;
use App\Services\Offers\PriceAlertService;
use App\Services\Offers\ProductOfferBundleService;
use App\Services\Offers\ProductOfferService;
use App\Services\Product\FilterInputSanitiser;

class DealsController extends Controller
{
    public function __construct(
        private readonly DealsService                        $dealsService,
        private readonly PriceAlertService                   $priceAlertService,
        private readonly DealAlertService                    $dealAlertService,
        private readonly CategoryRepository                  $categoryRepository,
        private readonly BrandRepository                     $brandRepository,
        private readonly ProductRepository                   $productRepository,
        private readonly ProductViewRepository               $productViewRepository,
        private readonly ProductSpecificationGroupRepository $specRepository,
        private readonly ProductOfferService                 $offerService,
        private readonly ProductOfferBundleService           $bundleService,
        private readonly CurrencyResolver                    $currencyResolver,
        private readonly FilterInputSanitiser                $inputSanitiser,
        private readonly WishlistRepository $wishlistRepository,
        private readonly CartRepository     $cartRepository,
        private readonly RegionSetRepository $regionSetRepository,

    ) {
        parent::__construct();
    }

    public function index(Request $request)
    {
        $siteId = SiteContext::getId();
        $userId = MemberAuth::id() ?? null;
        $sessionId = Session::get('cart_session_id');
        $member = MemberAuth::getMember();

        $categories = $this->categoryRepository->getAllWithProductCounts($siteId);
        $brands = $this->brandRepository->getAllWithProductCounts($siteId);
        $specificationGroups = $this->specRepository->getAllWithCounts($siteId);

        $offers = $this->offerService->getActiveOffers(10, $member, $siteId);
        $bundles = $this->bundleService->getActiveBundles(10, $member, $siteId);
        $deals = $this->dealsService->getTodaysDeals();

        // Ideally extracted to a MenuRepository — flagging as technical debt
        $menu = Menu::where('is_active', true)
            ->where('site_id', $siteId)
            ->where('menu_type', 'header')
            ->with(['items'])
            ->first();

        $currencyCode = $this->currencyResolver->resolveUpperCase();
        $currencySymbol = $this->currencyResolver->symbol($currencyCode);

        $wishlistProductIds = $this->wishlistRepository
            ->getProductIdsBySessionOrUser($userId, $sessionId);

        $cartProductIds = $this->cartRepository
            ->findBySessionOrUser($userId, $sessionId)
            ->pluck('product_id')
            ->all();

        $regionSets = $this->regionSetRepository->getActiveForSite($siteId);
        $selectedRegionSlug = $request->get('region', '');
        $selectedRegionSet = $selectedRegionSlug
            ? $regionSets->first(fn($r) => $r->slug === $selectedRegionSlug)
            : null;

        return $this->view('deals.index', [
            'categories' => $categories->toArray(),
            'brands' => $brands->toArray(),
            'menu' => $menu,
            'menuRenderer' => new MenuRenderer(),
            'specificationGroups' => $specificationGroups->toArray(),
            'deals' => $deals,
            'todaysDeals' => $this->dealsService->getTodaysDeals(10),
            'offers' => $offers,
            'bundles' => $bundles,
            'currencyCode' => $currencyCode,
            'currencySymbol' => $currencySymbol,
            'wishlistCount' => count($wishlistProductIds),
            'wishlistProductIds' => $wishlistProductIds,
            'cartCount' => $this->cartRepository->getCountBySessionOrUser($userId, $sessionId),
            'cartProductIds' => $cartProductIds,
            'regionSets'          => $regionSets->toArray(),
            'selectedRegionSlug'  => $selectedRegionSlug,
            'selectedRegionSetId' => $selectedRegionSet?->id,
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
        $input = $this->inputSanitiser->sanitise($request->all());
        $deals = $this->dealsService->getFilteredDeals($input);

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

    public function getProductModal(Request $request, mixed $id)
    {
        $safeId = $this->inputSanitiser->sanitiseId($id);

        if ($safeId === null) {
            return $this->jsonResponse(['success' => false, 'message' => 'Invalid product ID'], 422);
        }

        try {
            $product = $this->productRepository->find($safeId);

            if (!$product) {
                return $this->jsonResponse(['success' => false, 'message' => 'Product not found'], 404);
            }

            $userId = auth()->user()?->id ?? null;
            $sessionId = session_id() ?: $request->session()->getId();
            $ipAddress = $request->ip();

            if (!$userId || !$this->productViewRepository->hasRecentView($safeId, $userId, 60)) {
                $this->productViewRepository->trackView($product, $userId, $sessionId, $ipAddress);
            }

            $product->load([
                'images',
                'activeVariants.images',
                'activeVariants.merchants.merchant',
                'availableMerchants.merchant',
                'brand',
                'category',
                'approvedReviews',
                'specifications.group',
            ]);

            $relatedProducts = $this->productRepository->findRelated($product, 6);

            $similarItems = \App\Models\Product::where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->where('is_active', true)
                ->limit(6)
                ->get();

            $productData = $this->formatProductModalData($product);

            return $this->resourceResponse([
                'success' => true,
                'product' => $productData,
                'related_products' => $relatedProducts->map(fn($p) => $this->formatProductCard($p))->toArray(),
                'similar_items' => $similarItems->map(fn($p) => $this->formatProductCard($p))->toArray(),
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Error fetching product details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function formatProductModalData(mixed $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'price' => $product->price,
            'sale_price' => $product->sale_price,
            'brand' => ['id' => $product->brand?->id, 'name' => $product->brand?->name],
            'category' => ['id' => $product->category?->id, 'name' => $product->category?->name],
            'images' => $product->images->map(fn($img) => [
                'url' => $img->url,
                'alt' => $img->alt,
                'is_primary' => $img->is_primary,
            ])->toArray(),
            'variants' => $product->activeVariants->map(fn($variant) => [
                'id' => $variant->id,
                'name' => $variant->name,
                'sku' => $variant->sku,
                'price' => $variant->price,
                'sale_price' => $variant->sale_price,
                'attributes' => $variant->attributes,
                'in_stock' => $variant->in_stock,
                'stock_quantity' => $variant->stock_quantity,
                'images' => $variant->images->map(fn($img) => ['url' => $img->url, 'alt' => $img->alt])->toArray(),
                'merchants' => $variant->merchants->map(fn($m) => [
                    'id' => $m->id,
                    'merchant_id' => $m->merchant_id,
                    'name' => $m->merchant?->name,
                    'url' => $m->url,
                    'price' => $m->effective_price,
                    'sale_price' => $m->effective_sale_price,
                    'is_available' => $m->is_available,
                ])->toArray(),
            ])->toArray(),
            'merchants' => $product->availableMerchants->map(fn($m) => [
                'id' => $m->id,
                'merchant_id' => $m->merchant_id,
                'name' => $m->merchant?->name,
                'url' => $m->url,
                'price' => $m->effective_price,
                'sale_price' => $m->effective_sale_price,
                'is_available' => $m->is_available,
                'discount_percentage' => $m->discount_percentage,
                'has_discount' => $m->has_discount,
            ])->toArray(),
            'reviews' => $product->approvedReviews->map(fn($review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'title' => $review->title,
                'comment' => $review->comment,
                'author_name' => $review->author_name,
                'helpful_count' => $review->helpful_count,
                'is_verified_purchase' => $review->is_verified_purchase,
            ])->toArray(),
            'specifications' => $product->specifications->groupBy('category')->map(fn($specs, $category) => [
                'category' => $category,
                'items' => $specs->map(fn($spec) => ['key' => $spec->key, 'value' => $spec->value])->toArray(),
            ])->values()->toArray(),
            'average_rating' => $product->average_rating ?? 0,
            'review_count' => $product->approvedReviews->count(),
            'stock_quantity' => $product->stock_quantity,
            'in_stock' => $product->in_stock,
        ];
    }

    private function formatProductCard($product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->price,
            'sale_price' => $product->sale_price,
            'image' => $product->main_image_url ?? $product->images->first()?->url ?? null,
            'brand' => $product->brand?->name,
            'average_rating' => $product->average_rating ?? 0,
            'review_count' => $product->approvedReviews->count() ?? 0,
        ];
    }
}
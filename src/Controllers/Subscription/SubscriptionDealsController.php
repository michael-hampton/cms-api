<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Enums\Subscriptions\SubscriptionSortOption;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Subscriptions\SubscriptionBundleRepository;
use App\Services\Shopping\SubscriptionCatalogService;

/**
 * SubscriptionDealsController
 *
 * Serves the /subscriptions/deals page — same layout and filtering as the
 * main listing page but pre-filtered to on-sale plans only, plus active bundles.
 *
 * Reuses SubscriptionCatalogService directly (same as OneTimeSubscriptionsController)
 * and forces special_filter = 'on_sale'. No separate SubscriptionDealsService is
 * needed — the catalog service already owns this query logic.
 */
class SubscriptionDealsController extends Controller
{
    public function __construct(
        private readonly SubscriptionCatalogService   $catalogService,
        private readonly SubscriptionBundleRepository $bundleRepository
    )
    {
        parent::__construct();
    }

    public function index(Request $request)
    {
        $siteId = SiteContext::getId();

        // Force on_sale — this page only shows discounted plans.
        // All other filters are passed through so the user can still
        // narrow by site, delivery type, price range, etc.
        $filters = [
            'search' => $request->input('search'),
            'site_id' => $request->input('site_id'),
            'delivery_type' => $request->input('delivery_type'),
            'price_min' => $request->input('price_min'),
            'price_max' => $request->input('price_max'),
            'sort' => $request->input('sort', SubscriptionSortOption::PRICE_LOW_TO_HIGH->value),
            'per_page' => $request->input('per_page', 12),
            'page' => $request->input('page', 1),
            'tags' => $request->input('tags', []),
            'categories' => $request->input('categories'),
            'special_filter' => 'on_sale', // always enforced — cannot be overridden via query string
        ];

        $catalogData = $this->catalogService->getCatalog($filters);
        $availableSites = $this->catalogService->getAvailableSites();
        $priceRange = $this->catalogService->getPriceRange($filters['site_id'] ?? null);
        $availableCategories = $this->catalogService->getAvailableCategories($filters['site_id'] ?? null);
        $availableTags = $this->catalogService->getAvailableTags($filters['site_id'] ?? null);

        $categoryMappings = [
            ['name' => 'All', 'icon' => '🏠', 'color' => '#64748b'],
            ['name' => 'Monthly', 'icon' => '📅', 'color' => '#3b82f6'],
            ['name' => 'Digital Only', 'icon' => '📱', 'color' => '#8b5cf6'],
            ['name' => 'Print Edition', 'icon' => '📰', 'color' => '#ef4444'],
            ['name' => 'Best Value', 'icon' => '💰', 'color' => '#10b981'],
            ['name' => 'Premium', 'icon' => '⭐', 'color' => '#f59e0b'],
            ['name' => 'Annual', 'icon' => '📆', 'color' => '#06b6d4'],
            ['name' => 'Music', 'icon' => '🎵', 'color' => '#8b5cf6'],
            ['name' => 'Sport', 'icon' => '🏀', 'color' => '#f97316'],
            ['name' => 'Technology', 'icon' => '💻', 'color' => '#3b82f6'],
            ['name' => 'Fashion', 'icon' => '👗', 'color' => '#ec4899'],
            ['name' => 'Home & Garden', 'icon' => '🏡', 'color' => '#10b981'],
            ['name' => 'Food & Wine', 'icon' => '🍷', 'color' => '#ef4444'],
            ['name' => 'Travel', 'icon' => '✈️', 'color' => '#f59e0b'],
            ['name' => 'Equestrian', 'icon' => '🐎', 'color' => '#a78bfa'],
            ['name' => 'Games', 'icon' => '🎮', 'color' => '#facc15'],
            ['name' => 'Current Affairs', 'icon' => '🗞️', 'color' => '#64748b'],
            ['name' => 'Space', 'icon' => '🚀', 'color' => '#0ea5e9'],
        ];

        $lookup = array_column($categoryMappings, null, 'name');

        $merged = array_map(
            fn($name) => $lookup[$name] ?? ['name' => $name, 'icon' => '❓', 'color' => '#000000'],
            $availableCategories
        );

        // Active bundles are always deals by definition (bundle_price < total_price).
        $bundles = $this->bundleRepository
            ->getActiveBundles($siteId)
            ->map(fn($bundle) => [
                'id' => $bundle->id,
                'name' => $bundle->name,
                'slug' => $bundle->slug,
                'description' => $bundle->description,
                'bundle_price' => $bundle->bundle_price,
                'total_price' => $bundle->total_price,
                'savings_amount' => $bundle->getSavingsAmount(),
                'discount_percentage' => $bundle->getDiscountPercentage(),
                'plans' => $bundle->items->map(fn($item) => [
                    'id' => $item->subscriptionPlan->id,
                    'name' => $item->subscriptionPlan->name,
                    'delivery_type' => $item->delivery_type,
                ])->toArray(),
            ])->toArray();

        return $this->view('subscriptions/deals/index', [
            'plans' => $catalogData['data'],
            'bundles' => $bundles,
            'pagination' => [
                'current_page' => $catalogData['pagination']['current_page'],
                'total_pages' => $catalogData['pagination']['last_page'],
                'per_page' => $catalogData['pagination']['per_page'],
                'total' => $catalogData['pagination']['total'],
            ],
            'filters' => $filters,
            'available_sites' => $availableSites,
            'available_categories' => $merged,
            'available_tags' => $availableTags,
            'price_range' => $priceRange,
            'sort_options' => SubscriptionSortOption::cases(),
            'stripe_key' => $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? config('payment.stripe.publishable_key'),
        ]);
    }

    public function search(Request $request)
    {
        $siteId = SiteContext::getId();

        // Force on_sale — this page only shows discounted plans.
        // All other filters are passed through so the user can still
        // narrow by site, delivery type, price range, etc.
        $filters = [
            'search' => $request->input('search'),
            'site_id' => $request->input('site_id'),
            'delivery_type' => $request->input('delivery_type'),
            'price_min' => $request->input('price_min'),
            'price_max' => $request->input('price_max'),
            'sort' => $request->input('sort', SubscriptionSortOption::PRICE_LOW_TO_HIGH->value),
            'per_page' => $request->input('per_page', 12),
            'page' => $request->input('page', 1),
            'tags' => $request->input('tags', []),
            'categories' => $request->input('categories'),
            'special_filter' => 'on_sale', // always enforced — cannot be overridden via query string
        ];

        $catalogData = $this->catalogService->getCatalog($filters);

        // Handle AJAX filter requests
        return $this->jsonResponse([
            'success' => true,
            'plans' => $catalogData['data'],
            'pagination' => [
                'current_page' => $catalogData['pagination']['current_page'],
                'total_pages' => $catalogData['pagination']['last_page'],
                'per_page' => $catalogData['pagination']['per_page'],
                'total' => $catalogData['pagination']['total'],
            ],
        ]);
    }
}
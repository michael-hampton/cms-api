<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Enums\Subscriptions\SubscriptionSortOption;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Subscriptions\SubscriptionBundleRepository;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Shopping\OneTimeSubscriptionCheckoutService;
use App\Services\Shopping\OneTimeSubscriptionService;
use App\Services\Shopping\SubscriptionCatalogService;

class OneTimeSubscriptionsController extends Controller
{
    public function __construct(
        private readonly OneTimeSubscriptionService         $subscriptionService,
        private readonly OneTimeSubscriptionCheckoutService $checkoutService,
        private readonly StripePaymentProcessor       $stripeProcessor,
        private readonly SubscriptionCatalogService   $catalogService,
        private readonly SubscriptionBundleRepository $bundleRepository

    )
    {
        parent::__construct();
    }

    /**
     * Shop page - shows all subscriptions across all sites with filters
     */
    public function index(Request $request)
    {
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
            'special_filter' => $request->input('special_filter'),
        ];

        $siteId = (int)$filters['site_id'] ?? null;

        $catalogData = $this->catalogService->getCatalog($filters);

        // Handle AJAX filter requests
        if ($request->header('X-Requested-With') === 'XMLHttpRequest' || $request->input('ajax')) {
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

        $availableSites = $this->catalogService->getAvailableSites();
        $priceRange = $this->catalogService->getPriceRange($siteId ?? null);
        $availableCategories = $this->catalogService->getAvailableCategories($siteId ?? null);
        $availableTags = $this->catalogService->getAvailableTags($siteId ?? null);

        // Active bundles for the current site — shown in a dedicated section
        // above the individual plans listing.
        $bundles = $this->bundleRepository
            ->getActiveBundles()
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

        return $this->view('subscriptions/onetime/index', [
            'plans' => $catalogData['data'],
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
            'bundles' => $bundles
        ]);
    }

    public function search(Request $request)
    {
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
            'special_filter' => $request->input('special_filter'),
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

    public function checkout(Request $request)
    {
        $data = $request->all();
        $siteId = SiteContext::getId();

        $result = $this->checkoutService->processCheckout($data, $siteId);

        return $this->jsonResponse($result, $result['success'] ? 200 : 400);
    }

    public function confirmPayment(Request $request)
    {
        $paymentIntentId = $request->input('payment_intent_id');
        $orderId = $request->input('order_id');
        $siteId = SiteContext::getId();

        // Handle both single and multiple subscriptions
        $subscriptionIds = $request->input('subscription_ids');
        $subscriptionId = $request->input('subscription_id');

        if (!$paymentIntentId || !$orderId) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Missing required parameters'
            ], 400);
        }

        if (empty($subscriptionIds) && empty($subscriptionId)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Missing subscription information'
            ], 400);
        }

        // Convert to array for uniform handling
        if (!empty($subscriptionId)) {
            $subscriptionIds = [$subscriptionId];
        }

        $result = $this->stripeProcessor->handleOneTimeSubscriptionPayment(
            $paymentIntentId,
            $orderId,
            $siteId,
            $subscriptionIds
        );

        if ($result['success']) {
            // Activate all subscriptions
            foreach ($subscriptionIds as $subId) {
                $this->subscriptionService->activateSubscription($subId, $orderId);
            }

            // Update order status
            \App\Models\Order::where('id', $orderId)->update([
                'status' => 'completed',
                'payment_status' => 'paid'
            ]);
        }

        return $this->jsonResponse($result, $result['success'] ? 200 : 400);
    }

    public function showMultiple(Request $request)
    {
        $subscriptionIds = $request->input('ids');

        if (empty($subscriptionIds)) {
            return $this->redirect('/');
        }

        $subscriptions = [];
        foreach ($subscriptionIds as $id) {
            $details = $this->subscriptionService->getSubscriptionSummary($id);
            if ($details) {
                $subscriptions[] = $details;
            }
        }

        if (empty($subscriptions)) {
            return $this->redirect('/');
        }

        return $this->view('subscriptions/onetime/multiple-details', [
            'subscriptions' => $subscriptions
        ]);
    }

    /**
     * Single plan detail/purchase page (old index.php functionality)
     */
    public function show(int $id, Request $request)
    {
        $plan = $this->subscriptionService->getPlanWithPricingTiers($id);

        if (!$plan || !$plan->is_active) {
            return $this->redirect('/subscriptions');
        }

        // If this is a post-purchase view with subscription ID
        $subscriptionId = $request->input('subscription_id');
        if ($subscriptionId) {
            $subscriptionDetails = $this->subscriptionService->getSubscriptionSummary($subscriptionId);

            return $this->view('subscriptions/onetime/details', array_merge([
                'plan' => $plan,
                'stripe_key' => $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? config('payment.stripe.publishable_key')
            ], $subscriptionDetails));
        }

        // Regular plan view for purchase
        return $this->view('subscriptions/onetime/show', [
            'plan' => $plan,
            'stripe_key' => $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? config('payment.stripe.publishable_key')
        ]);
    }
}
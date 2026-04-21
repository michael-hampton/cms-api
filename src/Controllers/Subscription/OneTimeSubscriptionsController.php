<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Enums\Subscriptions\SubscriptionSortOption;
use App\Enums\Subscriptions\SubscriptionType;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionBundleRepository;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Currency\CurrencyResolver;
use App\Services\Reviews\ReviewService;
use App\Services\Shopping\OneTimeSubscriptionCheckoutService;
use App\Services\Shopping\OneTimeSubscriptionService;
use App\Services\Shopping\SubscriptionCatalogService;

class OneTimeSubscriptionsController extends Controller
{
    public function __construct(
        private readonly OneTimeSubscriptionService         $subscriptionService,
        private readonly OneTimeSubscriptionCheckoutService $checkoutService,
        private readonly StripePaymentProcessor             $stripeProcessor,
        private readonly SubscriptionCatalogService         $catalogService,
        private readonly SubscriptionBundleRepository       $bundleRepository,
        private readonly ReviewService                      $reviewService,
        private readonly CurrencyResolver                   $currencyResolver,
    )
    {
        parent::__construct();
    }

    /**
     * Shop page - shows all subscriptions across all sites with filters.
     *
     * Currency is resolved at the site level here because the index lists plans
     * from potentially multiple sites. The per-plan currency override is only
     * applied on the individual plan's show() page.
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

        // Site-level currency (used for the listing index where all prices are
        // in the same site currency; a per-plan currency is only meaningful on
        // the detail page where a single plan's currency field is available).
        $currencyCode = $this->currencyResolver->resolveUpperCase($siteId ?: null);
        $currencySymbol = $this->currencyResolver->symbol($currencyCode);

        $catalogData = $this->catalogService->getCatalog($filters);

        if ($request->header('X-Requested-With') === 'XMLHttpRequest' || $request->input('ajax')) {
            return $this->jsonResponse([
                'success' => true,
                'currencyCode' => $currencyCode,
                'currencySymbol' => $currencySymbol,
                'plans' => $catalogData['data']->map(function ($plan) {
                    $plan->delivery_type = !empty($plan->digital_download_url)
                        ? SubscriptionType::DIGITAL->value
                        : SubscriptionType::PRINTED->value;

                    return $plan;
                }),
                'pagination' => [
                    'current_page' => $catalogData['pagination']['current_page'],
                    'total_pages' => $catalogData['pagination']['last_page'],
                    'per_page' => $catalogData['pagination']['per_page'],
                    'total' => $catalogData['pagination']['total'],
                ],
            ]);
        }

        $availableSites = $this->catalogService->getAvailableSites();
        $priceRange = $this->catalogService->getPriceRange($siteId ?: null);
        $availableCategories = $this->catalogService->getAvailableCategories($siteId ?: null);
        $availableTags = $this->catalogService->getAvailableTags($siteId ?: null);

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
            'currencyCode' => $currencyCode,
            'currencySymbol' => $currencySymbol,
            'plans' => $catalogData['data']->map(function ($plan) {
                $plan->delivery_type = !empty($plan->digital_download_url)
                    ? SubscriptionType::DIGITAL->value
                    : SubscriptionType::PRINTED->value;

                return $plan;
            }),
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
            'bundles' => $bundles,
            'currency_code' => $currencyCode,
            'currency_symbol' => $currencySymbol,
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

        $siteId = (int)($filters['site_id'] ?? 0) ?: null;
        $currencyCode = $this->currencyResolver->resolveUpperCase($siteId);
        $currencySymbol = $this->currencyResolver->symbol($currencyCode);

        $catalogData = $this->catalogService->getCatalog($filters);

        return $this->jsonResponse([
            'success' => true,
            'currency_code' => $currencyCode,
            'currency_symbol' => $currencySymbol,
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

        $subscriptionIds = $request->input('subscription_ids');
        $subscriptionId = $request->input('subscription_id');

        if (!$paymentIntentId && !$orderId) {
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

        if (!empty($subscriptionId)) {
            $subscriptionIds = [$subscriptionId];
        }

        $result['success'] = true;

        if (!empty($paymentIntentId)) {
            $result = $this->stripeProcessor->handleOneTimeSubscriptionPayment(
                $paymentIntentId,
                $orderId,
                $siteId,
                $subscriptionIds
            );
        }


        $member = MemberAuth::getMember();
        $stripeCustomerId = $member->stripe_customer_id;

        if ($result['success']) {
            foreach ($subscriptionIds as $subId) {

                $subscription = Subscription::with('plan')->where('id', $subId)->first();

                if (empty($paymentIntentId)) {
                    $stripeSubscription = $this->stripeProcessor->createStripeSubscription(
                        $stripeCustomerId,
                        $subscription->plan,
                        $subscription,
                        false);

                    $subscription->update(['payment_subscription_id' => $stripeSubscription->id]);
                }

                $this->subscriptionService->activateSubscription($subId, $orderId);
            }

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
     * Single plan detail/purchase page.
     *
     * Currency is resolved from the plan's own `currency` field first, then
     * falls back to the site-level config. This allows a GBP plan to show £
     * even on a site whose default currency is USD.
     */
    public function show(int $id, Request $request)
    {
        $plan = $this->subscriptionService->getPlanWithPricingTiers($id);

        if (!$plan || !$plan->is_active) {
            return $this->redirect('/subscriptions');
        }

        // Resolve currency: plan's own field wins, then site default.
        $currencyCode = $this->currencyResolver->resolveForPlanUpperCase($plan->currency ?? null);
        $currencySymbol = $this->currencyResolver->symbol($currencyCode);

        $subscriptionId = $request->input('subscription_id');

        if ($subscriptionId) {
            $subscriptionDetails = $this->subscriptionService->getSubscriptionSummary($subscriptionId);

            return $this->view('subscriptions/onetime/details', array_merge([
                'plan' => $plan,
                'stripe_key' => $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? config('payment.stripe.publishable_key'),
                'currencyCode' => $currencyCode,
                'currencySymbol' => $currencySymbol,
            ], $subscriptionDetails));
        }

        $reviewData = $this->reviewService->getPlanReviews($id, 1, 5);
        $reviewStats = $this->reviewService->getPlanReviewSummary($id);
        $canReview = $this->reviewService->canUserReviewPlan($id);

        return $this->view('subscriptions/onetime/show', [
            'plan' => $plan,
            'reviewData' => $reviewData,
            'reviewStats' => $reviewStats,
            'canReview' => $canReview,
            'isAuthenticated' => MemberAuth::check(),
            'stripe_key' => $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? config('payment.stripe.publishable_key'),
            'currencyCode' => $currencyCode,
            'currencySymbol' => $currencySymbol,
        ]);
    }
}
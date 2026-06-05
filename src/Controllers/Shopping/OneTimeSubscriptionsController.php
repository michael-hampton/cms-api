<?php

namespace App\Controllers\Shopping;

use App\Controllers\Controller;
use App\Enums\Address\AddressType;
use App\Enums\Subscriptions\SubscriptionSortOption;
use App\Enums\Subscriptions\SubscriptionType;
use App\Events\Subscriptions\PaymentSucceeded;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Session\Session;
use App\Framework\Support\SiteContext;
use App\Models\Member;
use App\Models\Subscription;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Members\AddressRepository;
use App\Repositories\Subscriptions\SubscriptionBundleRepository;
use App\Services\Auth\CheckoutIdentityService;
use App\Services\Billing\Payments\OneTimeSubscriptionPaymentService;
use App\Services\Currency\CurrencyResolver;
use App\Services\Reviews\ReviewService;
use App\Services\Shopping\CartMigrationService;
use App\Services\Shopping\CartPersistenceService;
use App\Services\Shopping\CartService;
use App\Services\Shopping\OneTimeSubscriptionCheckoutService;
use App\Services\Shopping\OneTimeSubscriptionService;
use App\Services\Shopping\SubscriptionCatalogService;
use App\Services\Subscriptions\SubscriptionPaymentService;
use RuntimeException;
use Throwable;

class OneTimeSubscriptionsController extends Controller
{
    public function __construct(
        private readonly OneTimeSubscriptionService         $subscriptionService,
        private readonly OneTimeSubscriptionCheckoutService $checkoutService,
        private readonly SubscriptionPaymentService         $subscriptionPaymentService,
        private readonly SubscriptionCatalogService         $catalogService,
        private readonly SubscriptionBundleRepository       $bundleRepository,
        private readonly ReviewService                      $reviewService,
        private readonly CurrencyResolver                   $currencyResolver,
        private readonly OrderRepository                    $orderRepository,
        private readonly OneTimeSubscriptionPaymentService  $oneTimeSubscriptionPaymentService,
        private readonly CheckoutIdentityService            $identityService,
        private readonly CartPersistenceService             $cartPersistence,
        private readonly CartService                        $cartService,
        private readonly CartMigrationService               $cartMigration,
        private readonly AddressRepository                  $addressRepository,

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
            'per_page' => $request->input('per_page', 15),
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
                'plans' => $catalogData['data']->map(fn($plan) => $this->serializeCatalogPlan($plan)),
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
            'per_page' => $request->input('per_page', 15),
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
            'plans' => $catalogData['data']->map(fn($plan) => $this->serializeCatalogPlan($plan)),
            'pagination' => [
                'current_page' => $catalogData['pagination']['current_page'],
                'total_pages' => $catalogData['pagination']['last_page'],
                'per_page' => $catalogData['pagination']['per_page'],
                'total' => $catalogData['pagination']['total'],
            ],
        ]);
    }

    private function serializeCatalogPlan($plan): array
    {
        $bestSale = $plan->getBestSale();
        $price = $plan->getLowestEffectivePrice();
        $tier = $price['tier'] ?? null;
        $nextIssue = $plan->getNextIssue();
        $promotion = $plan->promotion()?->first();

        return [
            'id' => (int)$plan->id,
            'name' => (string)$plan->name,
            'slug' => (string)$plan->slug,
            'description' => $plan->description,
            'price' => (float)($price['min'] ?? $plan->price ?? 0),
            'sale_price' => isset($bestSale['sale']) ? (float)$bestSale['sale'] : null,
            'original_price' => isset($bestSale['original']) ? (float)$bestSale['original'] : null,
            'has_sale' => $bestSale !== null,
            'savings_pct' => $bestSale['savingPct'] ?? null,
            'pricing_tier_id' => $tier?->id,
            'billing_period' => $plan->billing_period,
            'delivery_type' => $plan->delivery_type,
            'categories' => $plan->categories ?? [],
            'tags' => $plan->tags ?? [],
            'features' => $plan->features ?? [],
            'is_featured' => (bool)$plan->is_featured,
            'is_limited_offer' => $plan->end_date && $plan->end_date->diffInDays(now()) <= 30,
            'release_date' => $plan->release_date?->format('Y-m-d'),
            'site_name' => $plan->site->name ?? $plan->site_name ?? '',
            'detail_url' => url('/press-stack/' . $plan->slug),
            'print_image_url' => $plan->print_image_url,
            'digital_image_url' => $plan->digital_image_url,
            'next_issue' => $nextIssue ? [
                'id' => (int)$nextIssue->id,
                'issue_number' => $nextIssue->issue_number,
                'cover_image' => $nextIssue->cover_image,
            ] : null,
            'promotion' => $promotion ? [
                'name' => $promotion->name,
                'type' => $promotion->type,
                'value' => $promotion->value,
            ] : null,
        ];
    }

    public function checkout(Request $request)
    {
        try {
            $data = $request->all();
            $siteId = SiteContext::getId();
            $member = MemberAuth::getMember();

            if (!$member && !empty($data['email'])) {
                $email = $data['email'];
                $sessionId = $this->cartService->getSessionId();

                try {
                    $result = $this->identityService->createAnonymous($email, $siteId, $data);
                    $member = Member::find($result->userId);
                    MemberAuth::login($member);

                    // Migrate any session-keyed cart items to the newly created member
                    // so that downstream services can find them by member id.
                    $this->cartMigration->migrateSessionCartToMember($member->id, $sessionId);
                } catch (RuntimeException $e) {
                    return $this->errorResponse($e->getMessage(), 400);
                }
            }

            if (!$member) {
                return $this->errorResponse('Authentication required', 401);
            }

            // Persist contact fields that createAnonymous may not have written.
            // Only fill blanks — never overwrite data already on the member record.
            $contactUpdates = array_filter([
                'first_name' => empty($member->first_name) ? ($data['first_name'] ?? null) : null,
                'last_name' => empty($member->last_name) ? ($data['last_name'] ?? null) : null,
                'phone' => empty($member->phone) ? ($data['phone'] ?? null) : null,
            ]);

            if (!empty($contactUpdates)) {
                $member->fill($contactUpdates);
                $member->save();
            }

            if (!empty($data['address']) && $member->addresses->count() === 0) {
                $this->addressRepository->create(
                    [
                        'member_id' => $member->id,
                        'address_line_1' => $data['address'],
                        'address_line_2' => $data['address2'],
                        'city' => $data['city'],
                        'state' => $data['state'],
                        'postcode' => $data['postal_code'],
                        'country' => $data['country'],
                        'is_default' => true,
                        'type' => AddressType::Billing->value,

                    ]
                );
            }

            $items = $this->cartService->getItems();

            if (!$items) {
                return $this->errorResponse('No items in cart', 400);
            }

            $result = $this->checkoutService->processCheckout($data, $siteId);

            if (!($result['success'] ?? false)) {
                return $this->errorResponse($result['message'] ?? 'Checkout failed', 400);
            }

            return $this->jsonResponse($result, 200);
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();

            return $this->errorResponse($e->getMessage(), 500);
        }

    }

    public function confirmPayment(Request $request)
    {
        $paymentIntentId = $request->input('payment_intent_id');
        $orderId = (int)$request->input('order_id');
        $siteId = SiteContext::getId();
        $paymentMethodId = $request->input('payment_method_id'); // new for recurring flow

        $subscriptionIds = $request->input('subscription_ids');
        $subscriptionId = $request->input('subscription_id');

        if (!$paymentIntentId && !$orderId) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Missing required parameters',
            ], 400);
        }

        if (empty($subscriptionIds) && empty($subscriptionId)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Missing subscription information',
            ], 400);
        }

        if (!empty($subscriptionId)) {
            $subscriptionIds = [$subscriptionId];
        }

        // ── One-time payment flow (paymentIntentId present) ───────────────────
        // Stripe already charged the card; verify the intent and record payment.
        if (!empty($paymentIntentId)) {
            $result = $this->oneTimeSubscriptionPaymentService->confirmPayment(
                $paymentIntentId,
                $orderId,
                $siteId,
                $subscriptionIds
            );

            if (!$result['success']) {
                return $this->jsonResponse($result, 400);
            }

            foreach ($subscriptionIds as $subId) {
                $this->subscriptionService->activateSubscription((int)$subId, $orderId);
            }

            $this->cartService->clear();
            $this->clearCheckoutSession();

            return $this->jsonResponse($result);
        }

        // ── Recurring subscription flow (no paymentIntentId) ─────────────────
        // Stripe manages future billing. We need to:
        //   1. Ensure the customer exists and the card is attached.
        //   2. Create the Stripe subscription (which sets up automatic billing).
        //   3. Record the payment and activate the local subscription.
        //
        // processSubscriptionPayment() handles steps 1–3 in full, using the same
        // path as the standard recurring checkout. We call it once per
        // subscription in the list (bundles may contain multiple).

        $member = MemberAuth::getMember();

        if (!$member) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $overallSuccess = true;

        foreach ($subscriptionIds as $subId) {
            $subscription = Subscription::with('plan')->find((int)$subId);

            if (!$subscription || !$subscription->plan) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => "Subscription {$subId} not found",
                ], 404);
            }

            // processSubscriptionPayment handles:
            //   - getOrCreateCustomer  (creates Stripe customer + saves stripe_customer_id)
            //   - payment method attachment + set as default
            //   - createStripeSubscription
            //   - payment record creation
            //   - subscription status update to active when Stripe confirms
            $paymentData = [];
            if (!empty($paymentMethodId)) {
                $paymentData['payment_method_id'] = $paymentMethodId;
            }

            $paymentData['order_id'] = $orderId;

            $paymentResult = $this->subscriptionPaymentService->processStripeSubscriptionPayment(
                $subscription,
                $subscription->plan,
                $paymentData
            );

            if (!$paymentResult['success']) {
                return $this->jsonResponse($paymentResult, 400);
            }

            // Persist the Stripe subscription ID so webhooks can locate this
            // subscription later (cancellations, renewals, etc.).
            $subscription->update([
                'payment_subscription_id' => $paymentResult['subscription_id'],
            ]);

            // Transition local status: PENDING → ACTIVE.
            // processSubscriptionPayment already set active on the Stripe side;
            // activateSubscription handles the DB transition + order linkage.
            //$this->subscriptionService->activateSubscription((int)$subId, $orderId);

            event(new PaymentSucceeded(
                subscriptionId: $subscription->id,
                paymentId: $paymentResult['payment_id'],
                amountCents: $subscription->price_paid_cents
                ?? (int)round($subscription->price * 100),
                currency: strtoupper($subscription->plan->currency),
            ));
        }

        // Mark the order complete through the repository (no raw static calls).
        $this->orderRepository->update($orderId, [
            'status' => 'completed',
            'payment_status' => 'paid'
        ]);

        $this->cartService->clear();
        $this->clearCheckoutSession();

        return $this->jsonResponse(['success' => true]);
    }

    private function clearCheckoutSession(): void
    {
        Session::forget('applied_voucher_code');
        Session::forget('checkout_token');
        Session::forget('pending_otp_email');
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
    public function show(string $slug, Request $request)
    {
        $plan = $this->subscriptionService->findBySlugWithPricingTiers($slug);

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

        $reviewData = $this->reviewService->getPlanReviews($plan->id, 1, 5);
        $reviewStats = $this->reviewService->getPlanReviewSummary($plan->id);
        $canReview = $this->reviewService->canUserReviewPlan($plan->id);

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

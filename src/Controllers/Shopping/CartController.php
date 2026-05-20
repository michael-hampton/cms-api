<?php

namespace App\Controllers\Shopping;

use App\Controllers\Controller;
use App\DTO\Checkout\DeliveryMethodConfig;
use App\Enums\Subscriptions\SubscriptionType;
use App\Events\Members\OrderCreatedByMember;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Session\Session;
use App\Framework\Support\SiteContext;
use App\Models\Member;
use App\Models\SubscriptionPlan;
use App\Repositories\Auth\OTPRepository;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Auth\CheckoutIdentityService;
use App\Services\Billing\OrderService;
use App\Services\Billing\Payments\SavedPaymentMethodService;
use App\Services\Billing\TaxCalculatorService;
use App\Services\Currency\CurrencyResolver;
use App\Services\Shipping\FulfilmentResolver;
use App\Services\Shipping\InternalBusinessDayEstimator;
use App\Services\Shipping\ShippingService;
use App\Services\Shopping\CartMigrationService;
use App\Services\Shopping\CartPersistenceService;
use App\Services\Shopping\CartService;
use App\Services\Shopping\CheckoutService;
use App\Services\Shopping\GiftResolutionService;
use App\Services\Shopping\OneTimeSubscriptionCheckoutService;
use DateTimeImmutable;
use Exception;
use RuntimeException;
use Throwable;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService                        $cartService,
        private readonly OrderService                       $orderService,
        private readonly CheckoutService                    $checkoutService,
        private readonly OneTimeSubscriptionCheckoutService $subscriptionCheckoutService,
        private readonly OrderRepository                    $orderRepository,
        private readonly ShippingService                    $shippingService,
        private readonly SavedPaymentMethodService          $savedPaymentMethodService,
        private readonly TaxCalculatorService               $taxCalculatorService,
        private readonly IssueDeliveryRepository            $issueDeliveryRepository,
        private readonly ProductRepository                  $productRepository,
        private readonly FulfilmentResolver                 $fulfilmentResolver,
        private readonly InternalBusinessDayEstimator       $businessDayEstimator,
        private readonly SubscriptionPlanRepository         $subscriptionPlanRepository,
        private readonly OTPRepository                      $OTPRepository,
        private readonly CheckoutIdentityService            $identityService,
        private readonly CartPersistenceService             $cartPersistence,
        private readonly CartMigrationService   $cartMigration,
        private GiftResolutionService           $giftResolutionService,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly CurrencyResolver       $currencyResolver,
    )
    {
        parent::__construct();
    }

    public function page()
    {
        $this->giftResolutionService->resolveAndSync($this->cartService->getSessionId(), MemberAuth::id());

        $items = $this->cartService->getItems();
        $subtotal = collect($items)->sum('subtotal');

        $startOptions = $this->calculateStartOptions($items);
        $deliveryMethod = DeliveryMethodConfig::default();

        $items = array_map(function ($item) use ($deliveryMethod) {
            $product = !empty($item['subscription_plan_id'])
                ? $this->subscriptionPlanRepository->find($item['subscription_plan_id'])
                : $this->productRepository->find($item['product_id']);

            $fulfilment = $this->fulfilmentResolver->resolve($product);
            $estimate = $this->businessDayEstimator->estimate(
                $fulfilment,
                $deliveryMethod,
                new DateTimeImmutable()
            );

            $trialDays = ($product instanceof SubscriptionPlan && $product->hasTrial())
                ? $product->trial_days
                : null;

            return array_merge($item, [
                'estimated_delivery' => $estimate->formattedRange(),
                'trial_days' => $trialDays,
            ]);
        }, $items);

        $shipping = $this->cartService->requiresShipping()
            ? $this->shippingService->calculateShipping($subtotal, Session::get('shipping_address', []))
            : 0.00;

        $tax = $this->calculateTax($subtotal, $shipping);

        $currencyCode = $this->currencyResolver->resolveUpperCase();
        $currencySymbol = $this->currencyResolver->symbol($currencyCode);

        $cartData = [
            'items' => $items,
            'total' => $this->cartService->getTotal(),
            'count' => $this->cartService->getCount(),
            'requiresShipping' => $this->cartService->requiresShipping(),
            'shipping' => $shipping,
            'tax' => $tax->taxCents / 100,
            'tax_rate' => $tax->rate,
            'subtotal' => $subtotal,
            'startOptions' => $startOptions,
            'currency' => $currencyCode,
            'currencySymbol' => $currencySymbol,
        ];

        return $this->view('cart/index', $cartData);
    }

    private function calculateStartOptions(array $items): array
    {
        $subscriptionItems = array_filter($items, fn($item) => !empty($item['options']['subscription_plan_id']));

        $now = new DateTimeImmutable('first day of this month 00:00:00');

        $allowedStartDates = [
            $now,
            $now->modify('first day of next month'),
            $now->modify('first day of +2 months'),
        ];

        $planIds = array_unique(array_column(array_column($subscriptionItems, 'options'), 'subscription_plan_id'));
        $plans = SubscriptionPlan::whereIn('id', $planIds)->get()->keyBy('id')->toArray();

        return array_reduce($subscriptionItems, function ($carry, $item) use ($plans, $allowedStartDates) {
            $planId = $item['options']['subscription_plan_id'];
            $plan = $plans[$planId] ?? null;
            $billingPeriod = $plan?->billing_period ?? 'monthly';

            $startDateOptions = array_map(fn($startDate) => [
                'start_date' => $startDate->format('Y-m-d'),
                'next_billing_date' => match ($billingPeriod) {
                    'monthly' => $startDate->modify('+1 month')->format('Y-m-d'),
                    'quarterly' => $startDate->modify('+3 months')->format('Y-m-d'),
                    'yearly' => $startDate->modify('+1 year')->format('Y-m-d'),
                    default => $startDate->modify('+1 month')->format('Y-m-d'),
                },
            ], $allowedStartDates);

            $carry[$planId] = [
                'item' => $item,
                'start_date_options' => $startDateOptions,
            ];

            return $carry;
        }, []);
    }

    private function calculateTax(float $subtotal, float $shipping): object
    {
        return $this->taxCalculatorService->calculateOrderTax(
            (int)round($subtotal * 100),
            (int)round($shipping * 100),
            'GB',
            null,
            null,
            MemberAuth::getMember()
        );
    }

    public function index()
    {
        $items = $this->cartService->getItems();
        $subtotal = collect($items)->sum('subtotal');

        $shipping = $this->cartService->requiresShipping()
            ? $this->shippingService->calculateShipping($subtotal, Session::get('shipping_address', []))
            : 0.00;

        $tax = $this->calculateTax($subtotal, $shipping);

        return $this->resourceResponse([
            'items' => $this->cartService->getItems(),
            'total' => $this->cartService->getTotal(),
            'count' => $this->cartService->getCount(),
            'requiresShipping' => $this->cartService->requiresShipping(),
            'shipping' => $shipping,
            'tax' => $tax->taxCents / 100,
            'tax_rate' => $tax->rate,
            'subtotal' => $subtotal,
        ]);
    }

    public function checkoutPage(Request $request)
    {
        $planId = $request->query('plan_id');
        $planSlug = $request->query('plan_slug');
        $isRenewal = $request->query('renewal') === 'true';

        $savedCards = MemberAuth::check()
            ? $this->savedPaymentMethodService->getMemberPaymentMethods(MemberAuth::getMember())
            : [];

        if ($planId || $planSlug) {
            if ($planSlug) {
                $plan = $this->subscriptionPlanRepository->findBySlug($planSlug);
                $planId = $plan?->id;
            }

            if ($planId) {
                return $this->subscriptionCheckout($planId, $isRenewal);
            }
        }

        $items = $this->cartService->getItems();
        $deliveryMethod = DeliveryMethodConfig::default();

        $items = array_map(function ($item) use ($deliveryMethod) {
            $product = !empty($item['subscription_plan_id'])
                ? $this->subscriptionPlanRepository->find($item['subscription_plan_id'])
                : $this->productRepository->find($item['product_id']);

            $fulfilment = $this->fulfilmentResolver->resolve($product);
            $estimate = $this->businessDayEstimator->estimate(
                $fulfilment,
                $deliveryMethod,
                new DateTimeImmutable()
            );

            $trialDays = ($product instanceof SubscriptionPlan && $product->hasTrial())
                ? $product->trial_days
                : null;

            return array_merge($item, [
                'estimated_delivery' => $estimate->formattedRange(),
                'trial_days' => $trialDays,
                'product_name' => $product->name
            ]);
        }, $items);

        $subscriptionItems = array_filter($items, fn($i) => !empty($i['subscription_plan_id']));

        $hasOneTimeSubscriptions = !empty(array_filter(
            $subscriptionItems,
            fn($i) => $this->subscriptionPlanRepository->find($i['subscription_plan_id'])?->isOneTime()
        ));

        $hasRecurringSubscriptions = !empty(array_filter(
            $subscriptionItems,
            fn($i) => !$this->subscriptionPlanRepository->find($i['subscription_plan_id'])?->isOneTime()
        ));

        $isOneTimeCart = !empty($subscriptionItems) && $hasOneTimeSubscriptions && !$hasRecurringSubscriptions;
        $isMixedSubscriptionCart = $hasOneTimeSubscriptions && $hasRecurringSubscriptions;

        $subtotal = collect($items)->sum('subtotal');

        $shipping = $this->cartService->requiresShipping()
            ? $this->shippingService->calculateShipping($subtotal, Session::get('shipping_address', []))
            : 0.00;

        $tax = $this->calculateTax($subtotal, $shipping);

        $currencyCode = $this->currencyResolver->resolveUpperCase();
        $currencySymbol = $this->currencyResolver->symbol($currencyCode);

        $cartData = [
            'items' => $items,
            'total' => $this->cartService->getTotal(),
            'count' => $this->cartService->getCount(),
            'requiresShipping' => $this->cartService->requiresShipping(),
            'shipping' => $shipping,
            'subtotal' => $subtotal,
            'savedCards' => $savedCards,
            'tax' => $tax->taxCents / 100,
            'tax_rate' => $tax->rate,
            'hasPreOrders' => $this->detectPreOrders($items),
            'member' => MemberAuth::check() ? MemberAuth::getMember() : null,
            'checkoutMode' => 'steps',
            'currency' => $currencyCode,
            'currencySymbol' => $currencySymbol,
            'isOneTimeCart' => $isOneTimeCart,
            'isMixedSubscriptionCart' => $isMixedSubscriptionCart,
        ];

        return $this->view('checkout/index', $cartData);
    }

    private function subscriptionCheckout(int $planId, bool $isRenewal = false)
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login?redirect=/checkout?plan_id=' . $planId);
        }

        $member = MemberAuth::getMember();
        $plan = $this->subscriptionPlanRepository->find($planId);

        if (!$plan || !$plan->is_active) {
            Session::put('flash_error', 'Subscription plan not found or unavailable.');
            return $this->redirect('/');
        }

        if (!$isRenewal && $this->subscriptionRepository->hasActiveSubscriptionToPlan($member->id, $planId)) {
            Session::put('flash_error', 'You already have an active subscription to this plan.');
            return $this->redirect('/' . SiteContext::slug() . '/member/subscriptions');
        }

        $items = $this->cartService->getItems();
        $hasPlan = collect($items)->contains(fn($item) => $item['subscription_plan_id'] === $planId);

        if ($isRenewal && !$hasPlan) {
            $this->cartService->addSubscriptionToCart(
                $planId,
                in_array(SubscriptionType::DIGITAL->value, $plan->getDeliveryOptions())
                    ? SubscriptionType::DIGITAL->value
                    : SubscriptionType::PRINTED->value
            );
        }

        return $this->view('checkout/subscription', [
            'plan' => $plan,
            'member' => $member,
            'isSubscription' => true,
            'requiresShipping' => empty($plan->digital_download_url),
        ]);
    }

    private function detectPreOrders(array $items): array
    {
        $preOrderItems = [];

        foreach ($items as $item) {
            $isPreOrder = false;
            $message = '';
            $shipDate = null;

            if (!empty($item['subscription_plan_id'])) {
                $plan = $this->subscriptionPlanRepository->find($item['subscription_plan_id']);

                if ($plan) {
                    $policy = $plan->availabilityPolicy();

                    if ($policy->isPreRelease() || $policy->isPreOrder()) {
                        $isPreOrder = true;
                        $message = $policy->getAvailabilityMessage();
                        $shipDate = $policy->getExpectedShipDate();
                    }
                }
            } elseif (!empty($item['product_id'])) {
                $product = $this->productRepository->find($item['product_id']);
                if ($product) {
                    $policy = $product->availabilityPolicy();
                    if ($policy->isPreOrder()) {
                        $isPreOrder = true;
                        $message = $policy->getAvailabilityMessage();
                        $shipDate = $policy->getExpectedShipDate();
                    }
                }
            }

            if ($isPreOrder) {
                $preOrderItems[] = [
                    'name' => $item['name'] ?? 'Unknown Item',
                    'message' => $message,
                    'ship_date' => $shipDate?->format('F j, Y'),
                ];
            }
        }

        return $preOrderItems;
    }

    public function processCheckout(Request $request)
    {
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
            'last_name'  => empty($member->last_name)  ? ($data['last_name']  ?? null) : null,
            'phone'      => empty($member->phone)      ? ($data['phone']      ?? null) : null,
        ]);

        if (!empty($contactUpdates)) {
            $member->fill($contactUpdates);
            $member->save();
        }

        $items = $this->cartService->getItems();

        if (!$items) {
            return $this->errorResponse('No items in cart', 400);
        }

        $subscriptionItems = array_filter($items, fn($item) => !empty($item['subscription_plan_id']));
        $productItems = array_filter($items, fn($item) => empty($item['subscription_plan_id']));

        if (!empty($subscriptionItems)) {
            $result = $this->processSubscription($request);
            $statusCode = $result['success'] ? 200 : 400;
            return $this->resourceResponse($result, $statusCode);
        }

        if (empty($productItems)) {
            return $this->errorResponse('No product items in cart', 400);
        }

        if (!empty($data['multi_merchant']) && $data['multi_merchant'] === true) {
            $result = $this->checkoutService->processMultiMerchantCheckout($data, $siteId);
            $statusCode = $result['success'] ? 200 : 400;
            event(new OrderCreatedByMember(MemberAuth::id(), SiteContext::getId()));
            $this->clearCheckoutSession();
            return $this->resourceResponse($result, $statusCode);
        }

        $result = $this->checkoutService->processCheckout($data, $siteId);
        $statusCode = $result['success'] ? 200 : 400;

        if (MemberAuth::check() && $result['success']) {
            event(new OrderCreatedByMember(MemberAuth::id(), SiteContext::getId(), $result['order_id']));
        }

        if ($result['success']) {
            $this->clearCheckoutSession();
        }

        return $this->resourceResponse($result, $statusCode);
    }

    private function processSubscription(Request $request): array
    {
        if (!MemberAuth::check()) {
            return ['success' => false, 'message' => 'Authentication required'];
        }

        $data = $request->all();
        $siteId = SiteContext::getId();

        try {
            $result = $this->subscriptionCheckoutService->processCheckout($data, $siteId);

            event(new OrderCreatedByMember(MemberAuth::id(), SiteContext::getId()));

            return $result;
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function clearCheckoutSession(): void
    {
        Session::forget('applied_voucher_code');
        Session::forget('checkout_token');
        Session::forget('pending_otp_email');
    }

    public function add(Request $request)
    {
        $productId = $request->input('product_id');
        $quantity = $request->input('quantity', 1);
        $options = $request->input('options', []);

        if (!$productId) {
            return $this->resourceResponse(['success' => false, 'message' => 'Product ID required'], 400);
        }

        $result = $this->cartService->addItem($productId, $quantity, $options);

        return $this->resourceResponse(array_merge($result, [
            'count' => $this->cartService->getCount(),
            'total' => $this->cartService->getTotal(),
        ]));
    }

    public function update(Request $request, int $id)
    {
        $quantity = $request->input('quantity');

        if ($quantity === null) {
            return $this->resourceResponse(['success' => false, 'message' => 'Quantity required'], 400);
        }

        $result = $this->cartService->updateQuantity($id, $quantity);

        return $this->resourceResponse(array_merge($result, [
            'count' => $this->cartService->getCount(),
            'total' => $this->cartService->getTotal(),
        ]));
    }

    public function remove(int $id)
    {
        $result = $this->cartService->removeItem($id);

        return $this->resourceResponse(array_merge($result, [
            'count' => $this->cartService->getCount(),
            'total' => $this->cartService->getTotal(),
        ]));
    }

    public function clear()
    {
        $this->cartService->clear();

        return $this->resourceResponse([
            'success' => true,
            'message' => 'Cart cleared',
            'count' => 0,
            'total' => 0,
        ]);
    }

    public function orderConfirmation(Request $request)
    {
        $orderNumber = $request->query('order_id');
        $checkoutId = $request->query('checkout_id');

        if (!$orderNumber && !$checkoutId) {
            return $this->redirect('/');
        }

        $orders = $checkoutId
            ? $this->orderRepository->getOrdersByCheckoutId($checkoutId)
            : collect([$this->orderService->getOrderByNumber($orderNumber)]);

        $orders = $orders->filter();

        if ($orders->isEmpty()) {
            return $this->redirect('/');
        }

        return $this->view('checkout/order-confirmation', [
            'orders' => $orders,
        ]);
    }

    public function addSubscription(Request $request)
    {
        $planId = $request->input('plan_id');
        $deliveryType = $request->input('delivery_type');
        $requestData = $request->all();

        $data = [
            'pricing_tier_id' => $requestData['pricing_id'] ?? $requestData['pricing_tier_id'] ?? null,
            'duration_months' => $requestData['duration_months'] ?? null,
            'issue_count' => $requestData['issues'] ?? null,
            'voucher_code' => $requestData['voucher_code'] ?? null,
            'delivery_type' => $requestData['delivery_type'],
        ];

        if (!$planId || !$deliveryType) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Plan ID and delivery type required',
            ], 400);
        }

        $result = $this->cartService->addSubscriptionToCart($planId, $deliveryType, $data);

        return $this->resourceResponse(array_merge($result, [
            'count' => $this->cartService->getCount(),
            'total' => $this->cartService->getTotal(),
            'items' => $this->cartService->getItems(),
        ]));
    }

    public function addSubscriptionBundle(Request $request)
    {
        $bundleId = $request->input('bundle_id');
        $result = $this->cartService->addSubscriptionBundleToCart($bundleId);

        return $this->resourceResponse(array_merge($result, [
            'count' => $this->cartService->getCount(),
            'total' => $this->cartService->getTotal(),
            'items' => $this->cartService->getItems(),
        ]));
    }

    public function addOffer(Request $request): JsonResponse
    {
        try {
            $offerId = $request->input('product_offer_id');
            $quantity = $request->input('quantity', 1);

            if (!$offerId) {
                return $this->errorResponse('Offer ID is required', 422);
            }

            $cartItem = $this->cartService->addOfferToCart($offerId, $quantity);

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Offer added to cart',
                'cart_item' => $cartItem,
            ], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function addBundle(Request $request): JsonResponse
    {
        try {
            $bundleId = $request->input('bundle_id');

            if (!$bundleId) {
                return $this->errorResponse('Bundle ID is required', 422);
            }

            $cartItems = $this->cartService->addBundleToCart($bundleId);

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Bundle added to cart',
                'cart_items' => $cartItems,
            ], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateStartDate(Request $request): JsonResponse
    {
        try {
            $cartItemId = $request->input('cart_item_id');
            $startDate = $request->input('start_date');
            $result = $this->cartService->updateStartDate($cartItemId, $startDate, MemberAuth::member()->id);

            if (!$result['success']) {
                return $this->errorResponse($result['message'], 400);
            }

            return $this->jsonResponse(['success' => true]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function verifyEmail(Request $request)
    {
        $email = $request->input('email');
        $siteId = SiteContext::getId();
        $sessionId = Session::getId();

        Session::put('cart_items', $this->cartService->getItems());

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse('Valid email is required', 422);
        }

        try {
            $result = $this->identityService->resolveIdentity($email, $sessionId, $siteId);

            if ($result->requiresOTP()) {
                $checkoutToken = $this->cartPersistence->snapshotCartForOTP($email, $sessionId, $siteId);
                $this->cartPersistence->setCheckoutToken($checkoutToken);
                Session::put('pending_otp_email', $email);
            }

            return $this->jsonResponse([
                'success' => true,
                'flow' => $result->requiresOTP() ? 'otp' : 'anonymous',
                'message' => $result->message,
                'expires_in' => $result->expiresIn,
            ]);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 429);
        }
    }

    public function checkPendingOTP()
    {
        $pendingEmail = Session::get('pending_otp_email');
        $sessionId = Session::getId();
        $siteId = SiteContext::getId();

        if (!$pendingEmail) {
            return $this->jsonResponse(['success' => true, 'has_pending' => false]);
        }

        $activeOTP = $this->OTPRepository->getActiveOTP($pendingEmail, $siteId, $sessionId);

        if ($activeOTP) {
            $expiresAt = new DateTimeImmutable($activeOTP->expires_at);
            $now = now_datetime();
            $remainingSeconds = max(0, $expiresAt->getTimestamp() - $now->getTimestamp());

            return $this->jsonResponse([
                'success' => true,
                'has_pending' => true,
                'email' => $pendingEmail,
                'expires_in' => $remainingSeconds,
                'attempts_remaining' => 5 - $activeOTP->attempts,
                'resends_remaining' => 5 - $activeOTP->resend_count,
            ]);
        }

        Session::forget('pending_otp_email');

        return $this->jsonResponse(['success' => true, 'has_pending' => false]);
    }

    public function verifyOTP(Request $request)
    {
        $email = $request->input('email');
        $otp = $request->input('otp');
        $siteId = SiteContext::getId();
        $sessionId = Session::getId();

        if (!$email || !$otp) {
            return $this->errorResponse('Email and OTP are required', 422);
        }

        try {
            $result = $this->identityService->verifyOTP($email, $otp, $sessionId, $siteId);
            $member = Member::find($result->userId);

            if (!$member) {
                return $this->errorResponse('Member not found', 404);
            }

            MemberAuth::login($member);

            // Migrate session cart items to the authenticated member before
            // restoring the OTP snapshot — order matters here.
            $this->cartMigration->migrateSessionCartToMember($member->id, $sessionId);

            $cartRestored = $this->cartPersistence->restoreCartAfterAuth($email, $siteId);

            Session::forget('pending_otp_email');
            Session::forget('checkout_token');

            return $this->jsonResponse([
                'success' => true,
                'message' => $result->message,
                'cart_restored' => $cartRestored,
                'member' => [
                    'id' => $member->id,
                    'email' => $member->email,
                    'first_name' => $member->first_name ?? '',
                    'last_name' => $member->last_name ?? '',
                ],
            ]);
        } catch (RuntimeException $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function cancelOTP(Request $request)
    {
        $email = $request->input('email');
        $sessionId = Session::getId();

        if ($email) {
            $this->OTPRepository->cancelOTP($sessionId, $email);
        }

        Session::forget('pending_otp_email');
        Session::forget('checkout_token');

        return $this->jsonResponse(['success' => true, 'message' => 'OTP flow cancelled']);
    }

    public function resendOTP(Request $request)
    {
        $email = $request->input('email');
        $siteId = SiteContext::getId();
        $sessionId = Session::getId();

        if (!$email) {
            return $this->errorResponse('Email is required', 422);
        }

        try {
            $result = $this->identityService->resendOTP($email, $sessionId, $siteId);

            if (!$result['success']) {
                return $this->jsonResponse(['success' => false, 'message' => $result['message']], 429);
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'New verification code sent',
                'expires_in' => $result['expires_in'] ?? null,
            ]);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getStatus()
    {
        $member = MemberAuth::getMember();
        $sessionId = Session::getId();
        $siteId = SiteContext::getId();
        $pendingOTPEmail = Session::get('pending_otp_email');

        $pendingCartItems = 0;
        if ($member) {
            $pendingCartItems = $this->cartPersistence->getSnapshotItemCount($member->email, $siteId);
        }

        return $this->jsonResponse([
            'success' => true,
            'authenticated' => $member !== null,
            'member' => $member ? [
                'id' => $member->id,
                'email' => $member->email,
                'first_name' => $member->first_name ?? '',
                'last_name' => $member->last_name ?? '',
                'anonymous' => $member->anonymous ?? false,
            ] : null,
            'session_id' => $sessionId,
            'pending_cart_items' => $pendingCartItems,
            'pending_otp_email' => $pendingOTPEmail,
        ]);
    }
}

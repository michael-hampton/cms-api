<?php

namespace App\Controllers\Shopping;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Session\Session;
use App\Framework\Support\SiteContext;
use App\Models\SubscriptionPlan;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Services\Billing\OrderService;
use App\Services\Billing\Payments\SavedPaymentMethodService;
use App\Services\Billing\TaxCalculatorService;
use App\Services\Shopping\CartService;
use App\Services\Shopping\CheckoutService;
use App\Services\Shopping\ShippingService;
use App\Services\Subscriptions\SubscriptionCheckoutService;
use DateTimeImmutable;
use Exception;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService               $cartService,
        private readonly OrderService              $orderService,
        private readonly CheckoutService             $checkoutService,
        private readonly SubscriptionCheckoutService $subscriptionCheckoutService,
        private readonly OrderRepository           $orderRepository,
        private readonly ShippingService           $shippingService,
        private readonly SavedPaymentMethodService $savedPaymentMethodService,
        private readonly TaxCalculatorService      $taxCalculatorService,
        private readonly IssueDeliveryRepository   $issueDeliveryRepository
    )
    {
        parent::__construct();
    }

    public function page()
    {
        $items = $this->cartService->getItems();
        $subtotal = collect($items)->sum('subtotal');
        $startOptions = $this->calculateStartOptions($items);

        $shipping = $this->cartService->requiresShipping()
            ? $this->shippingService->calculateShipping($subtotal, $_SESSION['shipping_address'] ?? [])
            : 0.00;

        $cartData = [
            'items' => $items,
            'total' => $this->cartService->getTotal(),
            'count' => $this->cartService->getCount(),
            'requiresShipping' => $this->cartService->requiresShipping(),
            'shipping' => $shipping,
            'tax' => $this->calculateTax($subtotal, $shipping),
            'subtotal' => $subtotal,
            'startOptions' => $startOptions
        ];

        return $this->view('cart/index', $cartData);
    }

    private function calculateStartOptions(array $items)
    {
        $subscriptionItems = array_filter($items, fn($item) => !empty($item['options']['subscription_plan_id']));

        $now = new DateTimeImmutable('first day of this month 00:00:00');

// 2. Pre-calculate the allowed start dates once
        $allowedStartDates = [
            $now,
            $now->modify('first day of next month'),
            $now->modify('first day of +2 months'),
        ];

        $planIds = array_unique(array_column(array_column($subscriptionItems, 'options'), 'subscription_plan_id'));
        $plans = SubscriptionPlan::whereIn('id', $planIds)->get()->toArray();

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

            // Keying by planId here
            $carry[$planId] = [
                'item' => $item,
                'start_date_options' => $startDateOptions,
            ];

            return $carry;
        }, []);
    }

    private function calculateTax(float $subtotal, float $shipping)
    {
        $taxResult = $this->taxCalculatorService->calculateOrderTax(
            (int)round($subtotal * 100),
            (int)round($shipping * 100),
            'GB',
            null,
            null,
            MemberAuth::getMember()
        );

        $taxCents = $taxResult['tax_cents']; // 1200
        return $taxCents / 100;
    }

    public function index()
    {
        $items = $this->cartService->getItems();

        $subtotal = collect($items)->sum('subtotal');

        $shipping = $this->cartService->requiresShipping()
            ? $this->shippingService->calculateShipping($subtotal, $_SESSION['shipping_address'] ?? [])
            : 0.00;

        return $this->resourceResponse([
            'items' => $this->cartService->getItems(),
            'total' => $this->cartService->getTotal(),
            'count' => $this->cartService->getCount(),
            'requiresShipping' => $this->cartService->requiresShipping(),
            'shipping' => $shipping,
            'tax' => $this->calculateTax($subtotal, $shipping),
            'subtotal' => $subtotal
        ]);
    }

    public function checkoutPage(Request $request)
    {
        $planId = $request->query('plan_id');
        $planSlug = $request->query('plan_slug');
        $isRenewal = $request->query('renewal') === 'true';

        $savedCards = MemberAuth::check() ?
            $this->savedPaymentMethodService->getMemberPaymentMethods(MemberAuth::getMember())
            : [];

        if ($planId || $planSlug) {
            if ($planSlug) {
                $plan = $this->subscriptionCheckoutService->getSubscriptionPlanBySlug($planSlug);
                $planId = $plan?->id;
            }

            if ($planId) {
                // Handle renewal differently
                if ($isRenewal) {
                    return $this->subscriptionCheckout($planId, true);
                }
                return $this->subscriptionCheckout($planId);
            }
        }

        $items = $this->cartService->getItems();

        $subtotal = collect($items)->sum('subtotal');

        $shipping = $this->cartService->requiresShipping()
            ? $this->shippingService->calculateShipping($subtotal, $_SESSION['shipping_address'] ?? [])
            : 0.00;

        $cartData = [
            'items' => $items,
            'total' => $this->cartService->getTotal(),
            'count' => $this->cartService->getCount(),
            'requiresShipping' => $this->cartService->requiresShipping(),
            'shipping' => $shipping,
            'subtotal' => $subtotal,
            'savedCards' => $savedCards,
            'tax' => $this->calculateTax($subtotal, $shipping)
        ];

        return $this->view('checkout/index', $cartData);
    }

    public function processCheckout(Request $request)
    {
        $data = $request->all();
        $siteId = SiteContext::getId();

        // Check if this is a subscription checkout
        if (!empty($data['subscription_plan_id'])) {
            return $this->processSubscription($request);
        }

        // Check if this is a multi-merchant checkout
        if (!empty($data['multi_merchant']) && $data['multi_merchant'] === true) {
            $result = $this->checkoutService->processMultiMerchantCheckout($data, $siteId);
            $statusCode = $result['success'] ? 200 : 400;
            return $this->resourceResponse($result, $statusCode);
        }

        $result = $this->checkoutService->processCheckout($data, $siteId);
        Session::forget('applied_voucher_code');

        $statusCode = $result['success'] ? 200 : 400;
        return $this->resourceResponse($result, $statusCode);
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

        $order = !empty($checkoutId) ? $this->orderRepository->getOrdersByCheckoutId($checkoutId)?->first()
            : $this->orderService->getOrderByNumber($orderNumber);

        if (!$order) {
            return $this->redirect('/');
        }

        return $this->view('checkout/order-confirmation', [
            'order' => $order,
            'orderNumber' => $order->order_number,
            'total' => $order->total,
            'subtotal' => $order->subtotal,
            'tax' => $order->tax,
            'shipping' => $order->shipping,
            'items' => $order->items,
            'customerEmail' => $order->user->email ?? 'N/A',
            'shippingAddress' => $order->shipping_address,
            'paymentMethod' => $order->payment_method,
            'status' => $order->status,
            'createdAt' => $order->created_at
        ]);
    }

    private function subscriptionCheckout(int $planId, bool $isRenewal = false)
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login?redirect=/checkout?plan_id=' . $planId);
        }

        $member = MemberAuth::member();
        $plan = $this->subscriptionCheckoutService->getSubscriptionPlan($planId);

        if (!$plan || !$plan->is_active) {
            $_SESSION['flash_error'] = 'Subscription plan not found or unavailable.';
            return $this->redirect('/');
        }

        // Check if already subscribed (skip for renewals)
        if (!$isRenewal && $this->subscriptionCheckoutService->hasActiveSubscription($member->id, $planId)) {
            $_SESSION['flash_error'] = 'You already have an active subscription to this plan.';
            return $this->redirect('/' . SiteContext::slug() . '/member/subscriptions');
        }

        return $this->view('checkout/subscription', [
            'plan' => $plan,
            'member' => $member,
            'isSubscription' => true,
            'requiresShipping' => empty($plan->digital_download_url),
        ]);
    }

    private function processSubscription(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        $data = $request->all();
        $member = MemberAuth::member();
        $siteId = SiteContext::getId();

        $result = $this->subscriptionCheckoutService->processSubscriptionCheckout(
            $member->id,
            $data,
            $siteId
        );

        Session::forget('applied_voucher_code');

        return $this->jsonResponse($result, $result['success'] ? 200 : 400);
    }

    public function addSubscription(Request $request)
    {
        $planId = $request->input('plan_id');
        $deliveryType = $request->input('delivery_type');
        $options = $request->input('options', []);

        if (!$planId || !$deliveryType) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Plan ID and delivery type required'
            ], 400);
        }

        $result = $this->cartService->addOneTimeSubscription($planId, $deliveryType, $options, $request->get('pricing_id') ?? null);

        return $this->resourceResponse(array_merge($result, [
            'count' => $this->cartService->getCount(),
            'total' => $this->cartService->getTotal(),
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
                'cart_item' => $cartItem
            ], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Add bundle to cart
     */
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
                'cart_items' => $cartItems
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
}
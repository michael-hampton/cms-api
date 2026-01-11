<?php

namespace App\Controllers;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Services\Members\OrderService;
use App\Services\Shop\CartService;
use App\Services\Shop\CheckoutService;
use App\Services\Subscriptions\SubscriptionCheckoutService;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService     $cartService,
        private readonly OrderService    $orderService,
        private readonly CheckoutService             $checkoutService,
        private readonly SubscriptionCheckoutService $subscriptionCheckoutService
    )
    {
        parent::__construct();
    }

    public function page()
    {
        $cartData = [
            'items' => $this->cartService->getItems(),
            'total' => $this->cartService->getTotal(),
            'count' => $this->cartService->getCount(),
        ];

        return $this->view('cart/index', $cartData);
    }

    public function index()
    {
        return $this->resourceResponse([
            'items' => $this->cartService->getItems(),
            'total' => $this->cartService->getTotal(),
            'count' => $this->cartService->getCount(),
        ]);
    }

    public function checkoutPage(Request $request)
    {
        $planId = $request->query('plan_id');
        $planSlug = $request->query('plan_slug');
        $isRenewal = $request->query('renewal') === 'true';

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

        $cartData = [
            'items' => $this->cartService->getItems(),
            'total' => $this->cartService->getTotal(),
            'count' => $this->cartService->getCount(),
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

        $result = $this->checkoutService->processCheckout($data, $siteId);

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

        if (!$orderNumber) {
            return $this->redirect('/');
        }

        $order = $this->orderService->getOrderByNumber($orderNumber);

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
            'isSubscription' => true
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

        $result = $this->cartService->addOneTimeSubscription($planId, $deliveryType, $options);

        return $this->resourceResponse(array_merge($result, [
            'count' => $this->cartService->getCount(),
            'total' => $this->cartService->getTotal(),
        ]));
    }
}
<?php

namespace App\Controllers;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderService;
use App\Services\VoucherService;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService     $cartService,
        private readonly OrderService    $orderService,
        private readonly CheckoutService $checkoutService
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

    public function checkoutPage()
    {
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
}
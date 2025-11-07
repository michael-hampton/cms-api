<?php
// App/Controllers/Api/CartController.php

namespace App\Controllers;

use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\MemberRepository;
use App\Repositories\OrderItemRepository;
use App\Repositories\OrderRepository;
use App\Services\CartService;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService
    ) {
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

        // Validate required fields
        $required = ['first_name', 'last_name', 'email', 'phone', 'address', 'city', 'postal_code', 'country'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'
                ], 400);
            }
        }

        // Get cart items
        $cartItems = $this->cartService->getItems();
        if (empty($cartItems)) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Cart is empty'
            ], 400);
        }

        // Calculate totals
        $subtotal = $this->cartService->getTotal();
        $tax = $subtotal * 0.1; // 10% tax
        $shipping = $subtotal >= 100 ? 0 : 10; // Free shipping over $100
        $total = $subtotal + $tax + $shipping;

        // Prepare shipping and billing addresses
        $shippingAddress = [
            'address' => $data['address'],
            'address2' => $data['address2'] ?? '',
            'city' => $data['city'],
            'state' => $data['state'] ?? '',
            'postal_code' => $data['postal_code'],
            'country' => $data['country']
        ];

        $billingAddress = $shippingAddress; // Use same address for now

        // Prepare order data
        $orderData = [
            'customer_name' => $data['first_name'] . ' ' . $data['last_name'],
            'customer_email' => $data['email'],
            'customer_phone' => $data['phone'],
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => $data['payment_method'] ?? 'card',
            'subtotal' => $subtotal,
            'tax' => $tax,
            'shipping' => $shipping,
            'discount' => 0,
            'total' => $total,
            'currency' => 'USD',
            'shipping_address' => $shippingAddress,
            'billing_address' => $billingAddress,
            'customer_notes' => $data['notes'] ?? ''
        ];

        // Prepare order items from cart
        $items = [];
        foreach ($cartItems as $cartItem) {
            $items[] = [
                'product_id' => $cartItem['product_id'],
                'product_name' => $cartItem['product_name'],
                'product_sku' => $cartItem['product_sku'] ?? '',
                'quantity' => $cartItem['quantity'],
                'unit_price' => $cartItem['price'],
                'subtotal' => $cartItem['subtotal'],
                'tax' => 0,
                'total' => $cartItem['subtotal']
            ];
        }

        try {
            // Create order using OrderService
            $orderService = new \App\Services\OrderService(
                new OrderRepository(),
                new OrderItemRepository(),
                new MemberRepository()
            );

            $order = $orderService->createOrder($orderData, $items, $siteId);

            // Clear cart after successful order
            $this->cartService->clear();

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Order placed successfully',
                'order_id' => $order->order_number,
                'total' => $total
            ]);
        } catch (\Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 500);
        }
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

    public function orderConfirmation(Request $request) {
        $orderNumber = $request->query('order_id');

        if (!$orderNumber) {
            return $this->redirect('/');
        }

        $orderService = new \App\Services\OrderService(
            new OrderRepository(),
            new OrderItemRepository(),
            new MemberRepository()
        );

        $order = $orderService->getOrderByNumber($orderNumber);

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
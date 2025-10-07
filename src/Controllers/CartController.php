<?php
// App/Controllers/Api/CartController.php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Services\CartService;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService
    ) {
        parent::__construct();
    }

    public function index()
    {
        return $this->jsonResponse([
            'items' => $this->cartService->getItems(),
            'total' => $this->cartService->getTotal(),
            'count' => $this->cartService->getCount(),
        ]);
    }

    public function add(Request $request)
    {
        $productId = $request->input('product_id');
        $quantity = $request->input('quantity', 1);
        $options = $request->input('options', []);

        if (!$productId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Product ID required'], 400);
        }

        $result = $this->cartService->addItem($productId, $quantity, $options);

        return $this->jsonResponse(array_merge($result, [
            'count' => $this->cartService->getCount(),
            'total' => $this->cartService->getTotal(),
        ]));
    }

    public function update(Request $request, int $id)
    {
        $quantity = $request->input('quantity');

        if ($quantity === null) {
            return $this->jsonResponse(['success' => false, 'message' => 'Quantity required'], 400);
        }

        $result = $this->cartService->updateQuantity($id, $quantity);

        return $this->jsonResponse(array_merge($result, [
            'count' => $this->cartService->getCount(),
            'total' => $this->cartService->getTotal(),
        ]));
    }

    public function remove(int $id)
    {
        $result = $this->cartService->removeItem($id);

        return $this->jsonResponse(array_merge($result, [
            'count' => $this->cartService->getCount(),
            'total' => $this->cartService->getTotal(),
        ]));
    }

    public function clear()
    {
        $this->cartService->clear();

        return $this->jsonResponse([
            'success' => true,
            'message' => 'Cart cleared',
            'count' => 0,
            'total' => 0,
        ]);
    }
}
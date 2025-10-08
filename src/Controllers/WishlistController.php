<?php

namespace App\Controllers;

use App\Framework\Http\Request;
use App\Services\WishlistService;

class WishlistController extends Controller
{
    public function __construct(
        private readonly WishlistService $wishlistService
    ) {
        parent::__construct();
    }

    public function index()
    {
        return $this->resourceResponse([
            'items' => $this->wishlistService->getItems(),
            'count' => $this->wishlistService->getCount(),
        ]);
    }

    public function add(Request $request)
    {
        $productId = $request->input('product_id');

        if (!$productId) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Product ID required'
            ], 400);
        }

        $result = $this->wishlistService->addItem($productId);

        return $this->resourceResponse(array_merge($result, [
            'count' => $this->wishlistService->getCount(),
        ]));
    }

    public function remove(int $productId)
    {
        $result = $this->wishlistService->removeItem($productId);

        return $this->resourceResponse(array_merge($result, [
            'count' => $this->wishlistService->getCount(),
        ]));
    }
}
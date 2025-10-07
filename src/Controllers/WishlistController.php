<?php
// App/Controllers/Api/WishlistController.php

namespace App\Controllers;

use App\Framework\Http\Request;
use App\Services\WishlistService;

class WishlistController extends Controller
{
    public function __construct(
        private readonly WishlistService $wishlistService
    )
    {
        parent::__construct();
    }

    public function index()
    {
        return $this->jsonResponse([
            'items' => $this->wishlistService->getItems(),
            'count' => $this->wishlistService->getCount(),
        ]);
    }
}

//    public function add(Request $request)
//    {
//        $productId = $request->input('product_id');
//
//        if (!$productId) {
//            return $this->jsonResponse(['success' => false, 'message' => 'Product ID required'], 400);
//        }
//
//        $result = $this->wishlistService->addItem($productId);
//
//        return response()->json(array_merge($result
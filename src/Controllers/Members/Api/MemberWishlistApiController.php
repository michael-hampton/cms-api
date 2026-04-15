<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Services\Shopping\WishlistService;

class MemberWishlistApiController extends Controller
{
    public function __construct(private WishlistService $wishlistService)
    {
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        if (!MemberAuth::check()) return $this->jsonResponse(['success' => false], 401);

        return $this->resourceResponse([
            'success' => true,
            'data' => $this->wishlistService->getItems()
        ]);
    }
}
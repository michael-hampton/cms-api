<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Repositories\OrderRepository;
use App\Services\WishlistService;

class MemberWishlistController extends Controller
{
    public function __construct(private WishlistService $wishlistService)
    {
        parent::__construct();
    }

    public function index() {

        return $this->view('member.wishlist', ['items' => $this->wishlistService->getItems()]);
    }
}
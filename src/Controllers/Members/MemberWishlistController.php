<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;
use App\Services\Members\WishlistService;

class MemberWishlistController extends Controller
{
    public function __construct(private WishlistService $wishlistService)
    {
        parent::__construct();
    }

    public function index()
    {
        $member = MemberAuth::getMember();

        return $this->view('member.wishlist', [
            'items' => $this->wishlistService->getItems(),
            'member' => $member,
            'site' => SiteContext::get()
        ]);
    }
}
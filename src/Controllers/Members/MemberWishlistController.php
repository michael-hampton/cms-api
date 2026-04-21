<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;

class MemberWishlistController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $member = MemberAuth::getMember();

        return $this->view('member.wishlist', [
            'member' => $member,
            'site' => SiteContext::get()
        ]);
    }
}
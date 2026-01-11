<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;
use App\Repositories\Members\PageLikeRepository;

class MemberLikedPagesController extends Controller
{
    public function __construct(
        private PageLikeRepository $pageLikeRepository
    ) {
        parent::__construct();
    }

    public function index()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::member();
        $siteId = SiteContext::getId();

        $likedPages = $this->pageLikeRepository->getMemberLikedPages($member->id, $siteId);
        $totalLikes = $this->pageLikeRepository->getMemberLikeCount($member->id, $siteId);

        return $this->view('member/liked-pages', [
            'member' => $member,
            'site' => SiteContext::get(),
            'likedPages' => $likedPages,
            'totalLikes' => $totalLikes
        ]);
    }
}
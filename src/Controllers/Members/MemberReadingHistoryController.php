<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;
use App\Repositories\Members\PageViewRepository;

class MemberReadingHistoryController extends Controller
{
    public function __construct(
        private PageViewRepository $pageViewRepository
    ) {
        parent::__construct();
    }

    public function index()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $recentlyViewed = $this->pageViewRepository->getRecentlyViewedPages($member->id, 50);
        $totalPagesRead = $this->pageViewRepository->getUniquePagesViewedByMember($member->id, $siteId);

        return $this->view('member/reading-history', [
            'member' => $member,
            'site' => SiteContext::get(),
            'recentlyViewed' => $recentlyViewed,
            'totalPagesRead' => $totalPagesRead
        ]);
    }
}
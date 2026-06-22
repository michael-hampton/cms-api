<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;
use App\Repositories\MemberInsights\MemberActivityRepository;
use App\Repositories\Members\BadgeRepository;
use App\Services\Members\BadgeAccessService;
use App\Services\Members\BadgeService;

class MemberActivityController extends Controller
{
    public function __construct(
        private readonly BadgeService             $badgeService,
        private readonly MemberActivityRepository $activityRepository,
        private readonly BadgeRepository          $badgeRepository,
        private readonly BadgeAccessService       $badgeAccess,
    )
    {
        parent::__construct();
    }

    public function index()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();
        $member->load(['badges', 'points']);
        $site = SiteContext::get();

        return $this->view('member/activity/dashboard', [
            'member' => $member,
            'site' => $site,
            'canAccessBadges' => $this->badgeAccess->canAccessBadges($member, (int) $site->id),
            'badgesRequireActiveSubscription' => $this->badgeAccess->badgesRequireActiveSubscription((int) $site->id),
        ]);
    }

    public function badges()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();
        $member->load(['badges']);

        $site = SiteContext::get();
        $siteId = (int) $site->id;

        if (!$this->badgeAccess->canAccessBadges($member, $siteId)) {
            return $this->redirect('/' . $site->slug . '/member/subscriptions');
        }

        // Get all badges for the site
        $allBadges = $this->badgeRepository->getActiveBadges($siteId);
        $earnedBadges = $member->badges;
        $unearnedBadges = $allBadges->filter(function ($badge) use ($earnedBadges) {
            return !$earnedBadges->contains('id', $badge->id);
        });

        // Get categories
        $categories = $allBadges->pluck('category')
            ->unique()
            ->filter()
            ->all();

        return $this->view('member/activity/badges', [
            'member' => $member,
            'site' => $site,
            'earnedBadges' => $earnedBadges,
            'unearnedBadges' => $unearnedBadges,
            'categories' => $categories,
            'canAccessBadges' => true,
            'badgesRequireActiveSubscription' => $this->badgeAccess->badgesRequireActiveSubscription($siteId),
        ]);
    }
}

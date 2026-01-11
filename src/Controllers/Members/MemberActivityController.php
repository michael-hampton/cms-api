<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;
use App\Repositories\Members\MemberActivityRepository;
use App\Services\Members\BadgeService;

class MemberActivityController extends Controller
{
    public function __construct(
        private BadgeService             $badgeService,
        private MemberActivityRepository $activityRepository
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

        $progress = $this->badgeService->getMemberProgress($member);
        $recentActivities = $this->activityRepository->getMemberActivities($member->id, 20);
        $activityTrends = $this->badgeService->getActivityTrends($member, 30);

        return $this->view('member/activity/dashboard', [
            'member' => $member,
            'site' => SiteContext::get(),
            'progress' => $progress,
            'recent_activities' => $recentActivities,
            'activity_trends' => $activityTrends
        ]);
    }

    public function badges()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();

        $member->load(['badges']);

        return $this->view('member/activity/badges', [
            'member' => $member,
            'site' => $member->site
        ]);
    }
}
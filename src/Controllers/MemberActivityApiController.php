<?php

namespace App\Controllers;

use App\Framework\Authorization\MemberAuth;
use App\Repositories\Members\MemberActivityRepository;
use App\Services\Members\BadgeService;

class MemberActivityApiController extends Controller
{
    public function __construct(
        private BadgeService             $badgeService,
        private MemberActivityRepository $activityRepository
    )
    {
        parent::__construct();
    }

    public function stats()
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::member();

        return $this->jsonResponse([
            'success' => true,
            'stats' => [
                'total_points' => $member->total_points,
                'badges_earned' => $member->badges()->count(),
                'activities' => $member->activity_stats
            ]
        ]);
    }

    public function trends()
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::member();
        $days = (int)($_GET['days'] ?? 30);

        $trends = $this->badgeService->getActivityTrends($member, $days);

        return $this->jsonResponse([
            'success' => true,
            'trends' => $trends
        ]);
    }

    public function badgeProgress()
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::member();
        $progress = $this->badgeService->getMemberProgress($member);

        return $this->jsonResponse([
            'success' => true,
            'progress' => $progress
        ]);
    }
}
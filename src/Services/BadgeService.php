<?php

namespace App\Services;

use App\Framework\Support\SiteContext;
use App\Models\Badge;
use App\Models\Member;
use App\Models\MemberActivity;
use App\Models\MemberBadge;
use App\Models\MemberPoint;
use App\Repositories\BadgeRepository;

class BadgeService
{
    private BadgeRepository $badgeRepository;

    public function __construct(BadgeRepository $badgeRepository)
    {
        $this->badgeRepository = $badgeRepository;
    }

    public function trackActivity(
        Member  $member,
        string  $activityType,
        ?string $entityType = null,
        ?int    $entityId = null,
        array   $metadata = [],
        int     $points = 0,
        ?int    $siteId = null
    ): MemberActivity
    {
        $siteId = $siteId ?? SiteContext::getId();
        $activity = $this->badgeRepository->createMemberActivity([
            'member_id' => $member->id,
            'site_id' => $siteId,
            'activity_type' => $activityType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
            'points' => $points,
            'activity_date' => now()
        ]);

        if ($points > 0) {
            $this->awardPoints($member, $points, "Activity: {$activityType}", $activityType, $activity->id);
        }

        $this->checkAndAwardBadges($member);

        return $activity;
    }

    public function awardPoints(
        Member  $member,
        int     $points,
        string  $reason,
        ?string $referenceType = null,
        ?int    $referenceId = null
    ): MemberPoint
    {
        return $this->badgeRepository->createMemberPoint([
            'member_id' => $member->id,
            'points' => $points,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'awarded_at' => now()
        ]);
    }

    public function checkAndAwardBadges(Member $member): array
    {
        $newBadges = [];

        $badges = $this->badgeRepository->getActiveBadgesForSite($member->site_id);

        foreach ($badges as $badge) {
            if ($this->badgeRepository->findMemberBadge($member->id, $badge->id)) {
                continue;
            }

            if ($badge->checkCriteria($member)) {
                $memberBadge = $this->awardBadge($member, $badge);
                $newBadges[] = $memberBadge;
            }
        }

        return $newBadges;
    }

    public function awardBadge(Member $member, Badge $badge): MemberBadge
    {
        $memberBadge = $this->badgeRepository->createMemberBadge([
            'member_id' => $member->id,
            'badge_id' => $badge->id,
            'earned_at' => now(),
            'criteria_met' => $badge->criteria,
            'is_visible' => true
        ]);

        if ($badge->points > 0) {
            $this->awardPoints(
                $member,
                $badge->points,
                "Badge earned: {$badge->name}",
                'badge',
                $badge->id
            );
        }

        return $memberBadge;
    }

    public function getActivityTrends(Member $member, int $days = 30): array
    {
        $startDate = now_datetime()->subDays($days);

        $activities = $this->badgeRepository->getMemberActivitiesSince($member->id, $startDate)
            ->groupBy(function ($activity) {
                return $activity->activity_date->format('Y-m-d');
            });

        $trends = [];
        for ($i = 0; $i < $days; $i++) {
            $date = now_datetime()->subDays($days - $i - 1)->format('Y-m-d');
            $trends[$date] = [
                'date' => $date,
                'count' => $activities->get($date)?->count() ?? 0,
                'points' => $activities->get($date)?->sum('points') ?? 0
            ];
        }

        return array_values($trends);
    }

    public function getMemberProgress(Member $member, ?int $siteId = null): array
    {
        $siteId = $siteId ?? SiteContext::getId();
        $memberArr = $member->toArray();
        $stats = $memberArr['activity_stats'] ?? [];
        $earnedBadges = $this->badgeRepository->getEarnedBadges($member);
        $availableBadges = $this->badgeRepository->getActiveBadgesForSite($siteId);

        $nextBadges = [];
        foreach ($availableBadges as $badge) {
            if ($earnedBadges->contains('id', $badge->id)) {
                continue;
            }

            $progress = $this->calculateBadgeProgress($member, $badge);


            if ($progress['percentage'] > 0) {
                $nextBadges[] = [
                    'badge' => $badge,
                    'progress' => $progress
                ];
            }
        }

        usort($nextBadges, fn($a, $b) => $b['progress']['percentage'] <=> $a['progress']['percentage']);

        return [
            'stats' => $stats,
            'total_points' => $memberArr['total_points'],
            'badges_earned' => $earnedBadges->count(),
            'badges_available' => $availableBadges->count(),
            'next_badges' => array_slice($nextBadges, 0, 5)
        ];
    }

    private function calculateBadgeProgress(Member $member, Badge $badge): array
    {
        $criteria = $badge->criteria;
        $totalCriteria = count($criteria);
        $metCriteria = 0;
        $details = [];

        foreach ($criteria as $rule) {
            $type = $rule['type'] ?? '';
            $operator = $rule['operator'] ?? '>=';
            $target = $rule['value'] ?? 0;

            $current = $this->getCurrentValue($member, $type);
            $met = $this->compareForProgress($current, $operator, $target);

            if ($met) {
                $metCriteria++;
            }

            $details[] = [
                'type' => $type,
                'current' => $current,
                'target' => $target,
                'met' => $met,
                'percentage' => $target > 0 ? min(100, ($current / $target) * 100) : 0
            ];
        }

        return [
            'percentage' => $totalCriteria > 0 ? ($metCriteria / $totalCriteria) * 100 : 0,
            'met' => $metCriteria,
            'total' => $totalCriteria,
            'details' => $details
        ];
    }

    private function getCurrentValue(Member $member, string $type)
    {
        switch ($type) {
            case 'comments_count':
                return $this->badgeRepository->getCommentsCount($member);
            case 'pages_read':
                return $this->badgeRepository->getDistinctPagesRead($member);
            case 'likes_given':
                return $this->badgeRepository->getLikesGivenCount($member);
            case 'member_days':
                return now_datetime()->diffInDays($member->created_at);
            case 'orders_count':
                return $this->badgeRepository->getCompletedOrdersCount($member->id);
            case 'total_spent':
                return $this->badgeRepository->getTotalSpent($member->id);
            default:
                return 0;
        }
    }

    private function compareForProgress($actual, $operator, $expected): bool
    {
        switch ($operator) {
            case '>=':
                return $actual >= $expected;
            case '>':
                return $actual > $expected;
            case '<=':
                return $actual <= $expected;
            case '<':
                return $actual < $expected;
            case '==':
                return $actual == $expected;
            case '!=':
                return $actual != $expected;
            default:
                return false;
        }
    }
}
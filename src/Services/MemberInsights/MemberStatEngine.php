<?php

namespace App\Services\MemberInsights;

use App\Framework\Support\Collection;
use App\Models\GiftedArticle;
use App\Models\MemberActivity;
use App\Models\MemberStat;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\MemberInsights\MemberActivityAnalyticsRepository;
use App\Repositories\Members\CommentRepository;
use App\Repositories\Members\PageLikeRepository;
use App\Repositories\Members\PageViewRepository;
use App\Repositories\Rewards\RewardsRepository;

class MemberStatEngine
{
    public function __construct(
        private readonly PageViewRepository                $pageViewRepository,
        private readonly PageLikeRepository                $pageLikeRepository,
        private readonly CommentRepository                 $commentRepository,
        private readonly OrderRepository                   $orderRepository,
        private readonly RewardsRepository                 $memberRewardRepository,
        private readonly MemberActivityAnalyticsRepository $analyticsRepository
    )
    {
    }

    public function rebuild(int $memberId, int $siteId): void
    {
        $activities = MemberActivity::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->get();

        $counts = $this->buildCounts($memberId, $siteId, $activities);

        // Write flat counters to member_stats via the existing repository contract.
        $this->analyticsRepository->upsert($memberId, $siteId, $counts);

        // Write the full nested payload to member_stats.data so the segment rule
        // evaluator can resolve dot-notation paths like scores.activity_score,
        // trends.7d_change, flags, etc.
        MemberStat::updateOrCreate(['member_id' => $memberId, 'site_id' => $siteId], array_merge($counts['counters'], ['data' => $counts]));
    }

    private function buildCounts(int $memberId, int $siteId, Collection $activities): array
    {
        return [
            'counters' => [
                'view_count' => $this->pageViewRepository->countForMember($memberId, $siteId),
                'like_count' => $this->pageLikeRepository->countForMember($memberId, $siteId),
                'comment_count' => $this->commentRepository->countForMember($memberId, $siteId),
                'order_count' => $this->orderRepository->countForMember($memberId, $siteId),
                'reward_claimed_count' => $this->memberRewardRepository->countClaimedForMember($memberId, $siteId),
                'articles_gifted_count' => $this->countArticlesGifted($memberId, $siteId),
                'articles_received_count' => $this->countArticlesReceived($memberId, $siteId),
            ],
            'summary' => $this->summary($activities),
            'scores' => $this->scores($activities),
            'behaviour' => $this->behaviour($activities),
            'trends' => $this->trends($activities),
            'interests' => $this->interests($activities),
            'flags' => $this->flags($activities),
        ];
    }

    private function countArticlesGifted(int $memberId, int $siteId): int
    {
        return GiftedArticle::where('gifted_by_member_id', $memberId)
            ->where('site_id', $siteId)
            ->count();
    }

    private function countArticlesReceived(int $memberId, int $siteId): int
    {
        return GiftedArticle::where('recipient_member_id', $memberId)
            ->where('site_id', $siteId)
            ->where('status', 'claimed')
            ->count();
    }

    private function summary(Collection $activities): array
    {
        if ($activities->isEmpty()) {
            return ['total_actions' => 0, 'active_days' => 0, 'streak_days' => 0];
        }

        return [
            'total_actions' => $activities->count(),
            'active_days' => $activities
                ->groupBy(fn($a) => $a->activity_date->format('Y-m-d'))
                ->count(),
            'streak_days' => $this->calculateStreak($activities),
        ];
    }

    private function calculateStreak(Collection $activities): int
    {
        $days = $activities
            ->map(fn($a) => $a->activity_date->format('Y-m-d'))
            ->unique()
            ->sort()
            ->values();

        if ($days->isEmpty()) {
            return 0;
        }

        $streak = 1;
        $best = 1;
        $prev = new \DateTime($days->last());

        // Walk backwards from the most recent active day.
        foreach ($days->reverse()->skip(1) as $day) {
            $current = new \DateTime($day);
            $diff = (int)$prev->diff($current)->days;

            if ($diff === 1) {
                $streak++;
                $best = max($best, $streak);
            } else {
                break;
            }

            $prev = $current;
        }

        return $best;
    }

    private function scores(Collection $activities): array
    {
        if ($activities->isEmpty()) {
            return ['activity_score' => 0, 'engagement_score' => 0];
        }

        $total = max($activities->count(), 1);
        $activityScore = min(100, (int)($total / 10));
        $engagementScore = min(100,
            ($activities->where('activity_type', 'comment')->count() * 3) +
            ($activities->where('activity_type', 'like')->count() * 2) +
            ($activities->where('activity_type', 'view')->count() * 1)
        );

        return [
            'activity_score' => $activityScore,
            'engagement_score' => $engagementScore,
        ];
    }

    private function behaviour(Collection $activities): array
    {
        if ($activities->isEmpty()) {
            return ['dominant_type' => null, 'profile_type' => 'no_activity'];
        }

        $dominant = $activities
            ->countBy('activity_type')
            ->sortByDesc()
            ->keys()
            ->first();

        $profileType = match ($dominant) {
            'view' => 'browsing_heavy',
            'like' => 'reactive_user',
            'comment' => 'engaged_contributor',
            'order' => 'purchasing_user',
            default => 'balanced_user',
        };

        return [
            'dominant_type' => $dominant,
            'profile_type' => $profileType,
        ];
    }

    private function trends(Collection $activities): array
    {
        if ($activities->isEmpty()) {
            return ['7d_change' => 0];
        }

        $daily = $activities
            ->groupBy(fn($a) => $a->activity_date->format('Y-m-d'))
            ->map(fn($group) => $group->count());

        $last7 = $daily->take(-7)->sum();
        $prev7 = $daily->slice(-14, 7)->sum();

        // Stored as a plain integer so segment rules can use numeric comparisons
        // (e.g. trends.7d_change < -20). Format as "%" only at the display layer.
        $change = $prev7 > 0
            ? (int)round((($last7 - $prev7) / $prev7) * 100)
            : 0;

        return ['7d_change' => $change];
    }

    // GiftedArticle has no repository yet. Two targeted count queries here are
    // preferable to introducing a repository solely to wrap them. Extract to
    // GiftedArticleRepository if gifting grows its own query surface.

    private function interests(Collection $activities): array
    {
        if ($activities->isEmpty()) {
            return ['top_entity_types' => [], 'top_entities' => []];
        }

        $byType = $activities->countBy('entity_type');

        $topEntities = $activities
            ->whereNotNull('entity_id')
            ->groupBy('entity_id')
            ->map(fn($group) => $group->count())
            ->sortByDesc()
            ->take(5);

        return [
            'top_entity_types' => $byType,
            'top_entities' => $topEntities,
        ];
    }

    private function flags(Collection $activities): array
    {
        if ($activities->isEmpty()) {
            return [];
        }

        $flags = [];
        $total = $activities->count();

        if ($total > 500) {
            $flags[] = 'high_activity';
        }

        $viewRatio = $activities->where('activity_type', 'view')->count() / max($total, 1);
        if ($viewRatio > 0.8) {
            $flags[] = 'lurker_profile';
        }

        return $flags;
    }
}
<?php

namespace App\Services\Members;

use App\Enums\BadgeCriteriaOperator;
use App\Enums\BadgeCriteriaType;
use App\Events\Badges\BadgeEarnedEvent;
use App\Events\Badges\PointsAwardedEvent;
use App\Framework\Database\Database;
use App\Framework\Queue\Dispatcher;
use App\Framework\Support\SiteContext;
use App\Jobs\EvaluateMemberBadgesJob;
use App\Models\Badge;
use App\Models\Member;
use App\Models\MemberActivity;
use App\Models\MemberBadge;
use App\Models\MemberPoint;
use App\Repositories\Members\BadgeRepository;

class BadgeService
{
    public function __construct(
        private readonly BadgeRepository $badgeRepository,
        private readonly Database $database,
        private readonly ?Dispatcher $dispatcher = null,
        private readonly ?BadgeAccessService $badgeAccess = null,
    )
    {
    }

    // =========================================================================
    // Admin CRUD
    // =========================================================================

    /**
     * List all badges for a site (paginated).
     */
    public function listForSite(int $siteId, int $page, int $perPage = 20, array $filters = []): mixed
    {
        return $this->badgeRepository->paginate($perPage, $page, $siteId, $filters);
    }

    /**
     * Fetch a single badge, scoped to the site.
     *
     * @throws \InvalidArgumentException if not found
     */
    public function findForSite(int $id, int $siteId): Badge
    {
        $badge = $this->badgeRepository->findForSite($id, $siteId);

        if ($badge === null) {
            throw new \InvalidArgumentException("Badge #{$id} not found.");
        }

        return $badge;
    }

    /**
     * Create a new badge for the site.
     *
     * @throws \InvalidArgumentException on validation failure or duplicate name
     */
    public function createBadge(array $payload, int $siteId): Badge
    {
        $this->validateCriteria($payload['criteria'] ?? []);

        if ($this->badgeRepository->existsByNameForSite($payload['name'], $siteId)) {
            throw new \InvalidArgumentException(
                "A badge named \"{$payload['name']}\" already exists for this site."
            );
        }

        return $this->database->transaction(function () use ($payload, $siteId) {
            return $this->badgeRepository->create([
                'site_id' => $siteId,
                'name' => trim($payload['name']),
                'description' => $payload['description'] ?? null,
                'icon' => $payload['icon'] ?? null,
                'criteria' => $payload['criteria'],
                'points' => $payload['points'] ?? 0,
                'is_active' => $payload['is_active'] ?? true,
                'slug' => $payload['slug'],
                'category' => $payload['category']
            ]);
        });
    }

    /**
     * Update an existing badge.
     *
     * @throws \InvalidArgumentException on validation failure, not found, or duplicate name
     */
    public function updateBadge(int $id, array $payload, int $siteId): Badge
    {
        $badge = $this->findForSite($id, $siteId);

        if (isset($payload['criteria'])) {
            $this->validateCriteria($payload['criteria']);
        }

        if (
            isset($payload['name'])
            && $this->badgeRepository->existsByNameForSite($payload['name'], $siteId, $id)
        ) {
            throw new \InvalidArgumentException(
                "A badge named \"{$payload['name']}\" already exists for this site."
            );
        }

        return $this->database->transaction(function () use ($badge, $payload) {
            $this->badgeRepository->update($badge->id, array_filter([
                'name' => isset($payload['name']) ? trim($payload['name']) : null,
                'description' => $payload['description'] ?? null,
                'icon' => $payload['icon'] ?? null,
                'criteria' => $payload['criteria'] ?? null,
                'points' => $payload['points'] ?? null,
                'is_active' => $payload['is_active'] ?? null,
            ], fn($v) => $v !== null));

            return $this->badgeRepository->find($badge->id);
        });
    }

    /**
     * Delete a badge.
     *
     * @throws \InvalidArgumentException if not found
     */
    public function deleteBadge(int $id, int $siteId): void
    {
        $badge = $this->findForSite($id, $siteId);

        $this->database->transaction(function () use ($badge) {
            $this->badgeRepository->delete($badge->id);
        });
    }

    // =========================================================================
    // Criteria validation
    // =========================================================================

    /**
     * @throws \InvalidArgumentException if any rule is structurally invalid
     */
    private function validateCriteria(array $criteria): void
    {
        if (empty($criteria)) {
            throw new \InvalidArgumentException('At least one criterion is required.');
        }

        foreach ($criteria as $index => $rule) {
            $position = $index + 1;

            if (empty($rule['type'])) {
                throw new \InvalidArgumentException("Criterion #{$position}: type is required.");
            }
            if (BadgeCriteriaType::tryFrom($rule['type']) === null) {
                throw new \InvalidArgumentException(
                    "Criterion #{$position}: \"{$rule['type']}\" is not a valid type."
                );
            }

            if (empty($rule['operator'])) {
                throw new \InvalidArgumentException("Criterion #{$position}: operator is required.");
            }
            if (BadgeCriteriaOperator::tryFrom($rule['operator']) === null) {
                throw new \InvalidArgumentException(
                    "Criterion #{$position}: \"{$rule['operator']}\" is not a valid operator."
                );
            }

            if (!isset($rule['value']) || !is_numeric($rule['value'])) {
                throw new \InvalidArgumentException(
                    "Criterion #{$position}: value must be a numeric."
                );
            }
        }
    }

    // =========================================================================
    // Existing engine methods
    // =========================================================================

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

        return $this->database->transaction(function () use ($member, $activityType, $entityType, $entityId, $metadata, $points, $siteId) {
            $activity = $this->badgeRepository->createMemberActivity([
                'member_id' => $member->id,
                'site_id' => $siteId,
                'activity_type' => $activityType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'metadata' => $metadata,
                'points' => $points,
                'activity_date' => now_datetime(),
            ]);

            if ($points > 0) {
                $this->awardPoints(
                    $member,
                    $points,
                    "Activity: {$activityType}",
                    $activityType,
                    $activity->id,
                    now_datetime()
                );
            }

            $this->database->afterCommit(function () use ($member) {
                $dispatcher = $this->dispatcher ?? app(Dispatcher::class);
                $dispatcher->dispatch(EvaluateMemberBadgesJob::for($member->id));
            });

            return $activity;
        });
    }

    public function awardPoints(
        Member     $member,
        int        $points,
        string     $reason,
        ?string    $referenceType = null,
        ?int       $referenceId = null,
        ?\DateTime $timestamp = null
    ): MemberPoint
    {
        $timestamp = $timestamp ?? now_datetime();

        $memberPoint = $this->badgeRepository->createMemberPoint([
            'member_id' => $member->id,
            'points' => $points,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'awarded_at' => $timestamp,
        ]);

        event(new PointsAwardedEvent($member, $memberPoint));

        return $memberPoint;
    }

    public function checkAndAwardBadges(Member $member): array
    {
        if (!$this->badgeAccess()->canAccessBadges($member, (int) $member->site_id)) {
            return [];
        }

        $newBadges = [];

        $badges = $this->badgeRepository->getActiveBadgesForSite($member->site_id);

        foreach ($badges as $badge) {
            if ($this->badgeRepository->findMemberBadge($member->id, $badge->id)) {
                continue;
            }

            if ($badge->checkCriteria($member)) {
                $newBadges[] = $this->awardBadge($member, $badge);
            }
        }

        return $newBadges;
    }

    public function awardBadge(Member $member, Badge $badge): MemberBadge
    {
        if (!$this->badgeAccess()->canAccessBadges($member, (int) $badge->site_id)) {
            throw new \InvalidArgumentException('An active subscription is required to earn badges.');
        }

        $now = now_datetime();

        return $this->database->transaction(function () use ($member, $badge, $now) {
            $memberBadge = $this->badgeRepository->createMemberBadge([
                'member_id' => $member->id,
                'badge_id' => $badge->id,
                'earned_at' => $now,
                'criteria_met' => $badge->criteria,
                'is_visible' => true,
            ]);

            if ($badge->points > 0) {
                $this->awardPoints(
                    $member,
                    $badge->points,
                    "Badge earned: {$badge->name}",
                    'badge',
                    $badge->id,
                    $now
                );
            }

            event(new BadgeEarnedEvent($member, $badge, $memberBadge));

            return $memberBadge;
        });
    }

    public function getActivityTrends(Member $member, int $days = 30): array
    {
        $startDate = now_datetime()->modify("-{$days} days");

        $activities = $this->badgeRepository->getMemberActivitiesSince($member->id, $startDate)
            ->groupBy(function ($activity) {
                return $activity->activity_date->format('Y-m-d');
            });

        $trends = [];
        for ($i = 0; $i < $days; $i++) {
            $date = now_datetime()->modify('-' . ($days - $i - 1) . ' days')->format('Y-m-d');
            $trends[$date] = [
                'date' => $date,
                'count' => $activities->get($date)?->count() ?? 0,
                'points' => $activities->get($date)?->sum('points') ?? 0,
            ];
        }

        return array_values($trends);
    }

    public function getMemberProgress(Member $member, ?int $siteId = null): array
    {
        $siteId = $siteId ?? SiteContext::getId();

        if (!$this->badgeAccess()->canAccessBadges($member, $siteId)) {
            return $this->badgeLockedProgress($member);
        }

        $stats = $member->activity_stats ?? [];
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
                    'progress' => $progress,
                ];
            }
        }

        usort($nextBadges, fn($a, $b) => $b['progress']['percentage'] <=> $a['progress']['percentage']);

        return [
            'stats' => $stats,
            'total_points' => $member->totalPoints,
            'badges_earned' => $earnedBadges->count(),
            'badges_available' => $availableBadges->count(),
            'next_badges' => array_slice($nextBadges, 0, 5),
        ];
    }

    public function calculateBadgeProgress(Member $member, Badge $badge): array
    {
        $criteria = $badge->criteria;
        $totalCriteria = count($criteria);
        $metCriteria = 0;
        $details = [];

        foreach ($criteria as $rule) {
            $type = BadgeCriteriaType::from($rule['type'] ?? '');
            $operator = BadgeCriteriaOperator::from($rule['operator'] ?? '>=');
            $target = $rule['value'] ?? 0;

            $current = $this->getCurrentValue($member, $type);
            $met = $operator->compare($current, $target);

            if ($met) {
                $metCriteria++;
            }

            $details[] = [
                'type' => $type->value,
                'current' => $current,
                'target' => $target,
                'met' => $met,
                'percentage' => $target > 0 ? min(100, ($current / $target) * 100) : 0,
            ];
        }

        return [
            'percentage' => $totalCriteria > 0 ? ($metCriteria / $totalCriteria) * 100 : 0,
            'met' => $metCriteria,
            'total' => $totalCriteria,
            'details' => $details,
        ];
    }

    private function getCurrentValue(Member $member, BadgeCriteriaType $type): mixed
    {
        return match ($type) {
            BadgeCriteriaType::COMMENTS_COUNT => $this->badgeRepository->getCommentsCount($member),
            BadgeCriteriaType::PAGES_READ => $this->badgeRepository->getDistinctPagesRead($member),
            BadgeCriteriaType::LIKES_GIVEN => $this->badgeRepository->getLikesGivenCount($member),
            BadgeCriteriaType::MEMBER_DAYS => now_datetime()->diff($member->created_at)->days,
            BadgeCriteriaType::ORDERS_COUNT => $this->badgeRepository->getCompletedOrdersCount($member->id),
            BadgeCriteriaType::TOTAL_SPENT => $this->badgeRepository->getTotalSpent($member->id),
        };
    }

    private function badgeAccess(): BadgeAccessService
    {
        return $this->badgeAccess ?? app(BadgeAccessService::class);
    }

    private function badgeLockedProgress(Member $member): array
    {
        return [
            'stats' => $member->activity_stats ?? [],
            'total_points' => $member->totalPoints ?? 0,
            'badges_earned' => 0,
            'badges_available' => 0,
            'next_badges' => [],
        ];
    }
}

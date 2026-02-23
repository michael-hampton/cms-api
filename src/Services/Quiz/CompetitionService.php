<?php

namespace App\Services\Quiz;

use App\Exceptions\CompetitionAlreadyEnteredException;
use App\Exceptions\CompetitionEntryNotUnlockedException;
use App\Exceptions\CompetitionNotAvailableException;
use App\Framework\Database\Database;
use App\Models\Competition;
use App\Models\Member;
use App\Repositories\Members\BadgeRepository;
use App\Repositories\Quiz\CompetitionRepository;
use App\Services\Members\BadgeService;

class CompetitionService
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly BadgeRepository       $badgeRepository,
        private readonly BadgeService          $badgeService,
        private readonly Database              $database,
    )
    {
    }

    // -------------------------------------------------------------------------
    // Listing
    // -------------------------------------------------------------------------

    public function getCompetitionsForSite(int $siteId, ?Member $member = null): array
    {
        $competitions = $this->competitionRepository->getActiveForSite($siteId);

        return $competitions->map(function (Competition $competition) use ($member) {
            return $this->decorateCompetition($competition, $member);
        })->toArray();
    }

    private function decorateCompetition(Competition $competition, ?Member $member): array
    {
        $hasEntered = false;
        $progress = null;
        $hasNotification = false;

        if ($member) {
            $hasEntered = (bool)$this->competitionRepository->findEntry($competition->id, $member->id);
            $progress = $this->getEntryProgress($competition, $member);
            $hasNotification = (bool)$this->competitionRepository->findNotification($competition->id, $member->id);
        }

        return [
            'id' => $competition->id,
            'title' => $competition->title,
            'description' => $competition->description,
            'slug' => $competition->slug,
            'status' => $competition->isComingSoon() ? 'coming_soon' : ($competition->hasEnded() ? 'ended' : 'active'),
            'starts_at' => $competition->starts_at?->format('M j'),
            'ends_at' => $competition->ends_at?->format('M j'),
            'prize' => $competition->prize_description,
            'entry_type' => $competition->entry_type,
            'external_url' => $competition->getExternalUrl(),
            'has_entered' => $hasEntered,
            'has_notification' => $hasNotification,
            'entry_count' => $this->competitionRepository->getEntryCount($competition->id),
            'progress' => $progress,
        ];
    }

    public function getEntryProgress(Competition $competition, Member $member): array
    {
        $criteria = $competition->getEntryCriteria();
        $details = [];
        $metCount = 0;
        $totalCount = count($criteria);

        foreach ($criteria as $criterion) {
            $result = $this->evaluateCriterion($criterion, $member);
            if ($result['met']) {
                $metCount++;
            }
            $details[] = $result;
        }

        return [
            'unlocked' => $totalCount === 0 || $metCount === $totalCount,
            'met' => $metCount,
            'total' => $totalCount,
            'percentage' => $totalCount > 0 ? (int)round(($metCount / $totalCount) * 100) : 100,
            'details' => $details,
        ];
    }

    // -------------------------------------------------------------------------
    // Entering
    // -------------------------------------------------------------------------

    /**
     * Evaluate a single entry criterion against a member.
     *
     * FIX 1: badge_ids — pluck 'badge_id' (the FK on MemberBadge), not 'id'
     * FIX 2: badge_count percentage — cast to int so assertSame(20, ...) passes
     * FIX 3: return_visits percentage — same int cast
     */
    private function evaluateCriterion(array $criterion, Member $member): array
    {
        $type = $criterion['type'] ?? '';
        $target = $criterion['value'] ?? 0;

        switch ($type) {

            // ---- badge_ids: member must hold ALL listed badge IDs ----
            case 'badge_ids':
                $required = $criterion['badge_ids'] ?? [];
                // MemberBadge stores the badge FK as badge_id, not id
                $earnedIds = $this->badgeRepository
                    ->getEarnedBadges($member)
                    ->pluck('badge_id')
                    ->toArray();

                $missing = array_diff($required, $earnedIds);
                $current = count($required) - count($missing);
                $total = count($required);
                $met = empty($missing);

                return [
                    'type' => $type,
                    'current' => $current,
                    'target' => $total,
                    'met' => $met,
                    'percentage' => $total > 0 ? (int)min(100, round(($current / $total) * 100)) : 100,
                ];

            // ---- badge_count: member must have earned at least N badges ----
            case 'badge_count':
                $current = $this->badgeRepository->getEarnedBadges($member)->count();
                $met = $current >= $target;

                return [
                    'type' => $type,
                    'current' => $current,
                    'target' => $target,
                    'met' => $met,
                    // FIX: cast to int — round() returns float, tests use assertSame(int)
                    'percentage' => $target > 0 ? (int)min(100, round(($current / $target) * 100)) : 100,
                ];

            // ---- return_visits: N qualifying days within X days ----
            case 'return_visits':
                $requiredVisits = $criterion['visits'] ?? 1;
                $requiredActions = $criterion['actions_per_visit'] ?? 0;
                $actionTypes = $criterion['action_types'] ?? [];
                $withinDays = $criterion['within_days'] ?? 30;

                $startDate = now_datetime()->modify("-{$withinDays} days");
                $activities = $this->badgeRepository->getMemberActivitiesSince($member->id, $startDate);

                $byDay = $activities->groupBy(fn($a) => $a->activity_date->format('Y-m-d'));
                $qualifyingDays = 0;

                foreach ($byDay as $dayActivities) {
                    if (empty($actionTypes)) {
                        $actionCount = $dayActivities->count();
                    } else {
                        $actionCount = $dayActivities
                            ->filter(fn($a) => in_array($a->activity_type, $actionTypes, true))
                            ->count();
                    }

                    if ($actionCount >= $requiredActions) {
                        $qualifyingDays++;
                    }
                }

                $met = $qualifyingDays >= $requiredVisits;

                return [
                    'type' => $type,
                    'current' => $qualifyingDays,
                    'target' => $requiredVisits,
                    'met' => $met,
                    'percentage' => $requiredVisits > 0 ? (int)min(100, round(($qualifyingDays / $requiredVisits) * 100)) : 100,
                    'actions_per_visit' => $requiredActions,
                    'action_types' => $actionTypes,
                    'within_days' => $withinDays,
                ];

            // ---- referral ----
            case 'referral':
                $current = (int)($criterion['referred_count'] ?? 0);
                $met = $current >= $target;

                return [
                    'type' => $type,
                    'current' => $current,
                    'target' => $target,
                    'met' => $met,
                    'percentage' => $target > 0 ? (int)min(100, round(($current / $target) * 100)) : 100,
                ];

            // ---- open / sponsored / raffle (no criteria required) ----
            default:
                return [
                    'type' => $type,
                    'current' => 1,
                    'target' => 1,
                    'met' => true,
                    'percentage' => 100,
                ];
        }
    }

    // -------------------------------------------------------------------------
    // Notifications
    // -------------------------------------------------------------------------

    public function getCompetition(int $siteId, string $slug, ?Member $member = null): ?array
    {
        $competition = $this->competitionRepository->findBySlug($siteId, $slug);

        if (!$competition) {
            return null;
        }

        return $this->decorateCompetition($competition, $member);
    }

    // -------------------------------------------------------------------------
    // Progress tracking
    // -------------------------------------------------------------------------

    public function getFeatured(int $siteId, ?Member $member = null): ?array
    {
        $competition = $this->competitionRepository->getFeaturedForSite($siteId);

        if (!$competition) {
            return null;
        }

        return $this->decorateCompetition($competition, $member);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * @throws CompetitionNotAvailableException
     * @throws CompetitionAlreadyEnteredException
     * @throws CompetitionEntryNotUnlockedException
     */
    public function enter(int $competitionId, Member $member, ?int $referredByMemberId = null): array
    {
        $competition = $this->competitionRepository->find($competitionId);

        if (!$competition || !$competition->isActive()) {
            throw new CompetitionNotAvailableException("Competition {$competitionId} is not available.");
        }

        if ($this->competitionRepository->findEntry($competitionId, $member->id)) {
            throw new CompetitionAlreadyEnteredException("Member {$member->id} has already entered competition {$competitionId}.");
        }

        $progress = $this->getEntryProgress($competition, $member);

        if (!$progress['unlocked']) {
            throw new CompetitionEntryNotUnlockedException("Member {$member->id} has not unlocked entry to competition {$competitionId}.");
        }

        return $this->database->transaction(function () use ($competition, $member, $referredByMemberId) {
            $entry = $this->competitionRepository->createEntry([
                'competition_id' => $competition->id,
                'member_id' => $member->id,
                'entered_at' => now_datetime(),
                'entry_method' => $competition->entry_type,
                'referred_by_member_id' => $referredByMemberId,
            ]);

            $this->badgeService->trackActivity(
                $member,
                'competition_entry',
                'competition',
                $competition->id,
                ['competition_title' => $competition->title],
            );

            return [
                'entry' => $entry,
                'entry_count' => $this->competitionRepository->getEntryCount($competition->id),
            ];
        });
    }

    public function requestNotification(int $competitionId, Member $member): bool
    {
        $competition = $this->competitionRepository->find($competitionId);

        if (!$competition) {
            throw new CompetitionNotAvailableException("Competition {$competitionId} not found.");
        }

        if ($this->competitionRepository->findNotification($competitionId, $member->id)) {
            return true;
        }

        $this->competitionRepository->createNotification([
            'competition_id' => $competitionId,
            'member_id' => $member->id,
            'notified_at' => now_datetime(),
        ]);

        return true;
    }
}
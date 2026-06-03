<?php

declare(strict_types=1);

namespace App\Services\Members;

use App\DTO\Members\DuplicateMatch;
use App\Enums\Member\DuplicateMemberMatchType;
use App\Framework\Support\Collection;
use App\Models\Member;
use App\Repositories\Members\MemberRepository;

/**
 * MemberDuplicateDetectionService
 *
 * Detects possible duplicate member accounts dynamically (no persistent flag
 * table in v1). Results are consumed at CRM profile-build time.
 *
 * Detection strategies (each returning DuplicateMatch[]):
 *   - Normalised email   — confidence 95
 *   - Stripe customer ID — confidence 95
 *   - Phone number       — confidence 85
 *   - Name + postcode    — confidence 60
 *
 * A member is never compared against themselves.
 * Each duplicate member ID appears at most once in the result (highest-
 * confidence match wins when a member matches on multiple signals).
 */
final class MemberDuplicateDetectionService
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
    ) {}

    /**
     * Detect all possible duplicates for the given member.
     *
     * Returns a Collection of DuplicateMatch DTOs ordered by confidence score
     * descending. Each duplicate member appears at most once.
     *
     * @return Collection<DuplicateMatch>
     */
    public function detectForMember(Member $member): Collection
    {
        // Keyed by duplicate member ID so we keep the highest-confidence
        // match when multiple signals point to the same member.
        $matchesByMemberId = [];

        foreach ($this->runAllStrategies($member) as $match) {
            $id = $match->duplicateMember->id;

            if (!isset($matchesByMemberId[$id])
                || $match->confidenceScore > $matchesByMemberId[$id]->confidenceScore
            ) {
                $matchesByMemberId[$id] = $match;
            }
        }

        return collect(array_values($matchesByMemberId))
            ->sortByDesc(fn(DuplicateMatch $m) => $m->confidenceScore)
            ->values();
    }

    /**
     * Returns the single highest-confidence duplicate match for the member,
     * or null if no duplicates are found.
     *
     * Suitable for the CRM banner (one primary warning at a time).
     */
    public function detectBestMatchForMember(Member $member): ?DuplicateMatch
    {
        return $this->detectForMember($member)->first();
    }

    // ─── Private strategy runners ─────────────────────────────────────────────

    /**
     * Run all detection strategies and yield every DuplicateMatch found.
     *
     * @return DuplicateMatch[]
     */
    private function runAllStrategies(Member $member): array
    {
        return array_merge(
            $this->findByEmail($member),
            $this->findByStripeCustomerId($member),
            $this->findByPhone($member),
            $this->findByNameAndPostcode($member),
        );
    }

    /**
     * High-confidence: exact normalised email match (score 95).
     *
     * @return DuplicateMatch[]
     */
    private function findByEmail(Member $member): array
    {
        return $this->memberRepository
            ->findPossibleDuplicatesByEmail($member)
            ->map(fn(Member $dup) => new DuplicateMatch(
                duplicateMember:  $dup,
                matchType:        DuplicateMemberMatchType::Email,
                confidenceScore:  DuplicateMemberMatchType::Email->confidenceScore(),
            ))
            ->all();
    }

    /**
     * High-confidence: same Stripe customer ID (score 95).
     *
     * @return DuplicateMatch[]
     */
    private function findByStripeCustomerId(Member $member): array
    {
        return $this->memberRepository
            ->findPossibleDuplicatesByStripeCustomerId($member)
            ->map(fn(Member $dup) => new DuplicateMatch(
                duplicateMember:  $dup,
                matchType:        DuplicateMemberMatchType::StripeCustomer,
                confidenceScore:  DuplicateMemberMatchType::StripeCustomer->confidenceScore(),
            ))
            ->all();
    }

    /**
     * Medium-high confidence: same phone number (score 85).
     *
     * @return DuplicateMatch[]
     */
    private function findByPhone(Member $member): array
    {
        return $this->memberRepository
            ->findPossibleDuplicatesByPhone($member)
            ->map(fn(Member $dup) => new DuplicateMatch(
                duplicateMember:  $dup,
                matchType:        DuplicateMemberMatchType::Phone,
                confidenceScore:  DuplicateMemberMatchType::Phone->confidenceScore(),
            ))
            ->all();
    }

    /**
     * Lower confidence: same last name + billing postcode (score 60).
     *
     * @return DuplicateMatch[]
     */
    private function findByNameAndPostcode(Member $member): array
    {
        return $this->memberRepository
            ->findPossibleDuplicatesByNameAndPostcode($member)
            ->map(fn(Member $dup) => new DuplicateMatch(
                duplicateMember:  $dup,
                matchType:        DuplicateMemberMatchType::NamePostcode,
                confidenceScore:  DuplicateMemberMatchType::NamePostcode->confidenceScore(),
            ))
            ->all();
    }
}
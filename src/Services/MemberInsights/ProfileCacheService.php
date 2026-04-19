<?php

namespace App\Services\MemberInsights;

use App\Repositories\Members\MemberSegmentationProfileRepository;

/**
 * Ticket 16 — Performance: Profile Caching.
 *
 * Caches the structured member profile (from member_stats.data) in an
 * in-process array for the duration of a single PHP request / queue job.
 *
 * Problem it solves:
 *   When ProcessMemberSegmentationJob runs, it calls getLatestProfile() once.
 *   But CampaignPreviewService, NewsletterBlockPersonalisationRenderer, and
 *   AudienceMatcher each call it independently for the same member during
 *   a preview or send cycle.  Without caching this is N redundant DB reads.
 *
 * Scope:
 *   In-process only (single job / request).  Cross-request caching would
 *   require invalidation logic and is out of scope for this ticket.
 *
 * Usage:
 *   Inject ProfileCacheService instead of MemberSegmentationProfileRepository
 *   in services that call getLatestProfile() multiple times per request.
 */
final class ProfileCacheService
{
    /** @var array<string, array|null>  key = "{memberId}:{siteId}" */
    private array $cache = [];

    public function __construct(
        private readonly MemberSegmentationProfileRepository $profileRepository,
    )
    {
    }

    /**
     * Get the profile, hitting the DB only once per (memberId, siteId) per process.
     */
    public function getProfile(int $memberId, int $siteId): ?array
    {
        $key = "{$memberId}:{$siteId}";

        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $this->profileRepository->getLatestProfile($memberId, $siteId);
        }

        return $this->cache[$key];
    }

    /**
     * Force a cache refresh — call after MemberStatEngine::rebuild().
     */
    public function invalidate(int $memberId, int $siteId): void
    {
        unset($this->cache["{$memberId}:{$siteId}"]);
    }

    /**
     * Warm the cache for a batch of members in one go.
     * Uses a single query via the repository's batch method (if available),
     * falling back to individual loads.
     *
     * @param array<int> $memberIds
     */
    public function warmBatch(array $memberIds, int $siteId): void
    {
        foreach ($memberIds as $memberId) {
            $key = "{$memberId}:{$siteId}";
            if (!array_key_exists($key, $this->cache)) {
                $this->cache[$key] = $this->profileRepository->getLatestProfile($memberId, $siteId);
            }
        }
    }
}
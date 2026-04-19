<?php

namespace App\Services\MemberInsights\Audiences;

use App\Models\Member;

/**
 * Evaluates audience membership for a single member.
 *
 * Contract:
 *   matches(Member, array $profile, string $audienceKey): bool
 *
 * The $profile array is the structured output of MemberStatEngine::rebuild()
 * as stored in member_stats.data.  Callers are responsible for loading it
 * before calling this service; we do not perform any DB access here.
 *
 * Usage in newsletters / campaigns:
 *   $profile = $profileRepository->getLatestProfile($member->id, $siteId);
 *   $visible = $audienceMatcher->matches($member, $profile, $block->audience_key);
 *
 * The service is intentionally stateless so it can be called in loops
 * (e.g. rendering all blocks for a newsletter) without re-instantiation.
 */
final class AudienceMatcher
{
    public function __construct(
        private readonly AudienceRegistry $registry,
    )
    {
    }

    /**
     * Returns true if the member's profile satisfies the audience resolver.
     *
     * @param Member $member The member being evaluated (kept for future
     *                        resolver signatures that may need model data).
     * @param array $profile Structured profile from MemberStatEngine.
     * @param string $audienceKey Key from AudienceRegistry::all().
     *
     * @throws \InvalidArgumentException if the audience key is not registered.
     */
    public function matches(Member $member, array $profile, string $audienceKey): bool
    {
        $audiences = $this->registry->all();

        if (!array_key_exists($audienceKey, $audiences)) {
            throw new \InvalidArgumentException(
                "Unknown audience key: [{$audienceKey}]. "
                . "Register it in AudienceRegistry::all() first."
            );
        }

        $resolver = $audiences[$audienceKey]['resolver'];

        return (bool)$resolver($profile);
    }

    /**
     * Returns all audience keys that the member belongs to.
     * Used by CampaignPreviewService and the analytics snapshot.
     *
     * @return string[]
     */
    public function resolveAll(Member $member, array $profile): array
    {
        $matched = [];

        foreach ($this->registry->all() as $key => $entry) {
            if ((bool)($entry['resolver'])($profile)) {
                $matched[] = $key;
            }
        }

        return $matched;
    }
}
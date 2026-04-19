<?php

namespace App\Services\Newsletter;

use App\Models\Member;
use App\Repositories\MemberInsights\MemberSegmentationProfileRepository;
use App\Services\MemberInsights\Audiences\AudienceMatcher;

/**
 * Ticket 9 — Personalisation Rendering Layer.
 *
 * Filters a collection of newsletter blocks for a specific member using
 * the AudienceMatcher.  The result is a reduced block array that the
 * existing NewsletterPageBuilderService / SlotRenderer renders without
 * any knowledge of audiences.
 *
 * Block visibility rules (from ticket spec):
 *   - audience_key === null  → always show (universal content)
 *   - audience_key set       → show only if member is in that audience
 *
 * This class has no rendering concern — it only answers "which blocks
 * should this member see?" so the renderer stays unaware of audiences.
 *
 * Integration point:
 *   NewsletterContentResolver::resolveCustomBlocks() should call
 *   filterBlocksForMember() before handing blocks to the pipeline.
 */
final class NewsletterBlockPersonalisationRenderer
{
    public function __construct(
        private readonly AudienceMatcher                     $audienceMatcher,
        private readonly MemberSegmentationProfileRepository $profileRepository,
    )
    {
    }

    /**
     * Return only the blocks this member should see.
     *
     * @param array $blocks Raw block array (may include 'audience_key').
     * @param Member|null $member Null = render all blocks (e.g. admin preview without member).
     * @param int $siteId
     * @return array Filtered block array, preserving original order.
     */
    public function filterBlocksForMember(array $blocks, ?Member $member, int $siteId): array
    {
        if ($member === null) {
            // No member context — show all blocks (admin preview mode).
            return $blocks;
        }

        $profile = $this->profileRepository->getLatestProfile($member->id, $siteId) ?? [];

        return array_values(array_filter($blocks, function (array $block) use ($member, $profile): bool {
            $audienceKey = $block['audience_key'] ?? null;

            if ($audienceKey === null) {
                return true;
            }

            try {
                return $this->audienceMatcher->matches($member, $profile, $audienceKey);
            } catch (\InvalidArgumentException) {
                // Unknown audience key — treat as universally visible so
                // a misconfigured key never silently hides content.
                return true;
            }
        }));
    }

    /**
     * Build a block visibility debug report (used by CampaignPreviewService
     * and the admin preview endpoint).
     *
     * @return array<int, array{key: string, audience_key: string|null, visible: bool, reason: string}>
     */
    public function buildVisibilityReport(array $blocks, ?Member $member, int $siteId): array
    {
        $profile = $member
            ? ($this->profileRepository->getLatestProfile($member->id, $siteId) ?? [])
            : [];

        $report = [];

        foreach ($blocks as $block) {
            $key = $block['key'] ?? ($block['type'] ?? 'unknown');
            $audienceKey = $block['audience_key'] ?? null;

            if ($audienceKey === null || $member === null) {
                $report[] = [
                    'key' => $key,
                    'audience_key' => $audienceKey,
                    'visible' => true,
                    'reason' => $audienceKey === null ? 'no audience restriction' : 'no member context (admin mode)',
                ];
                continue;
            }

            try {
                $visible = $this->audienceMatcher->matches($member, $profile, $audienceKey);
                $reason = $visible
                    ? "audience: {$audienceKey}"
                    : "not in audience: {$audienceKey}";
            } catch (\InvalidArgumentException $e) {
                $visible = true;
                $reason = "unknown audience key — defaulting to visible";
            }

            $report[] = [
                'key' => $key,
                'audience_key' => $audienceKey,
                'visible' => $visible,
                'reason' => $reason,
            ];
        }

        return $report;
    }
}
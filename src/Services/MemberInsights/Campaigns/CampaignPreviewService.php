<?php

namespace App\Services\MemberInsights\Campaigns;

use App\Models\Member;
use App\Repositories\MemberInsights\CampaignRepository;
use App\Repositories\MemberInsights\MemberSegmentationProfileRepository;
use App\Services\MemberInsights\Audiences\AudienceMatcher;
use App\Services\Members\Consents\ConsentQueryService;
use App\Services\Newsletter\NewsletterPageBuilderService;

/**
 * Ticket 7 — Campaign Preview & Debug System.
 *
 * Returns a structured payload describing exactly what a given member would
 * receive for a campaign or newsletter, and WHY each block is shown or hidden.
 *
 * Output contract:
 * {
 *   "audiences":      string[],          // audience keys the member belongs to
 *   "channel":        string,            // channel that would be used
 *   "consent":        bool,              // whether consent passes for that channel
 *   "blocks": [
 *     { "key": string, "visible": bool, "reason": string }
 *   ],
 *   "rendered_html":  string             // what the member would actually see
 * }
 *
 * Rules:
 *   - No writes — read-only preview.
 *   - rendered_html is built by the existing block rendering stack; blocks
 *     with audience_key that does NOT match are simply omitted.
 *   - "reason" strings are human-readable for the admin UI.
 */
final class CampaignPreviewService
{
    public function __construct(
        private readonly CampaignRepository                  $campaignRepository,
        private readonly MemberSegmentationProfileRepository $profileRepository,
        private readonly AudienceMatcher                     $audienceMatcher,
        private readonly ConsentQueryService                 $consentQuery,
        private readonly NewsletterPageBuilderService        $pageBuilder,
    )
    {
    }

    /**
     * Generate a full preview + debug payload for a member / campaign pair.
     *
     * @param Member $member
     * @param int $campaignId
     * @param int $siteId
     * @return array
     */
    public function preview(Member $member, int $campaignId, int $siteId): array
    {
        $campaign = $this->campaignRepository->find($campaignId, ['segment']);

        if ($campaign === null) {
            throw new \InvalidArgumentException("Campaign [{$campaignId}] not found.");
        }

        $profile = $this->profileRepository->getLatestProfile($member->id, $siteId) ?? [];

        // ── Audiences ──────────────────────────────────────────────────────
        $audienceKeys = $this->audienceMatcher->resolveAll($member, $profile);

        // ── Channel & consent ──────────────────────────────────────────────
        $channel = $campaign->channel instanceof \App\Enums\Member\CampaignChannel
            ? $campaign->channel->value
            : (string)$campaign->channel;

        $consentCode = $this->resolveConsentCode(
            \App\Enums\Member\CampaignPurpose::tryFrom($campaign->purpose->value ?? ''),
            \App\Enums\Member\CampaignChannel::tryFrom($channel),
        );

        $hasConsent = $consentCode !== null
            ? $this->safeHasConsent($member, $consentCode)
            : true; // transactional = no consent gate

        // ── Block visibility ───────────────────────────────────────────────
        $rawBlocks = $this->resolveBlocks($campaign);
        $blockDebug = $this->evaluateBlocks($rawBlocks, $audienceKeys);

        // ── Rendered HTML (only visible blocks) ───────────────────────────
        $visibleBlocks = array_filter($rawBlocks, fn($b, $i) => $blockDebug[$i]['visible'], ARRAY_FILTER_USE_BOTH);
        $renderedHtml = $this->renderBlocks(array_values($visibleBlocks), $campaign, $member, $siteId);

        return [
            'audiences' => $audienceKeys,
            'channel' => $channel,
            'consent' => $hasConsent,
            'blocks' => $blockDebug,
            'rendered_html' => $renderedHtml,
        ];
    }

    // -------------------------------------------------------------------------

    private function resolveConsentCode(
        ?\App\Enums\Member\CampaignPurpose $purpose,
        ?\App\Enums\Member\CampaignChannel $channel
    ): ?string
    {
        if ($purpose === null) {
            return null;
        }

        if (!$purpose->requiresConsent()) {
            return null;
        }

        return match (true) {
            $purpose === \App\Enums\Member\CampaignPurpose::MARKETING => 'marketing_email',
            $purpose === \App\Enums\Member\CampaignPurpose::PRODUCT_UPDATES => 'communication_preferences',
            default => null,
        };
    }

    private function safeHasConsent(Member $member, string $code): bool
    {
        try {
            return $this->consentQuery->hasConsent($member, $code);
        } catch (\Throwable) {
            return false;
        }
    }

    private function resolveBlocks(object $campaign): array
    {
        // Campaigns store blocks in a JSON column identical to newsletters.
        $blocks = $campaign->content_blocks ?? $campaign->blocks ?? [];

        if (is_string($blocks)) {
            $blocks = json_decode($blocks, true) ?? [];
        }

        return is_array($blocks) ? $blocks : [];
    }

    private function evaluateBlocks(array $blocks, array $memberAudienceKeys): array
    {
        $result = [];

        foreach ($blocks as $block) {
            $key = $block['key'] ?? ($block['type'] ?? 'unknown');
            $audienceKey = $block['audience_key'] ?? null;

            if ($audienceKey === null) {
                $result[] = [
                    'key' => $key,
                    'visible' => true,
                    'reason' => 'no audience restriction',
                ];
                continue;
            }

            $inAudience = in_array($audienceKey, $memberAudienceKeys, true);

            $result[] = [
                'key' => $key,
                'visible' => $inAudience,
                'reason' => $inAudience
                    ? "audience: {$audienceKey}"
                    : "not in audience: {$audienceKey}",
            ];
        }

        return $result;
    }

    private function renderBlocks(array $blocks, object $campaign, Member $member, int $siteId): string
    {
        if (empty($blocks)) {
            return '';
        }

        // Reuse the newsletter page builder's slot rendering path.
        // We synthesise a single-slot layout identical to what SendCampaignJob produces.
        $fakeNewsletter = $this->buildFakeNewsletter($campaign, $blocks);

        $context = new \App\Services\Newsletter\DTOs\NewsletterRenderContext(
            siteId: $siteId,
            newsletter: $fakeNewsletter,
            member: $member,
            sendId: null,
            includeTracking: false,
        );

        return $this->pageBuilder->renderSlotBlocks($blocks, $context);
    }

    /** Build a transient newsletter-like object for rendering purposes. */
    private function buildFakeNewsletter(object $campaign, array $blocks): object
    {
        $newsletter = new \App\Models\Newsletter();
        $newsletter->id = 0;
        $newsletter->title = $campaign->name ?? 'Campaign Preview';
        $newsletter->content_blocks = $blocks;
        $newsletter->content_type = 'custom_blocks';

        return $newsletter;
    }
}
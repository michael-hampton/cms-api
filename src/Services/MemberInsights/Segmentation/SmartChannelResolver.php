<?php

namespace App\Services\MemberInsights\Segmentation;

use App\Enums\Member\CampaignChannel;
use App\Models\Campaign;
use App\Repositories\MemberInsights\CampaignDeliveryRepository;
use App\Repositories\MemberInsights\CampaignEventRepository;

/**
 * Ticket 15 — Smart Channel Selection (Behaviour-Driven).
 *
 * Replaces the static email→push→notification fallback chain in ChannelResolver
 * with a behaviour-aware ordering that picks the channel a member actually
 * responds to.
 *
 * Algorithm:
 *   1. Compute a responsiveness score per channel from past delivery + event data.
 *   2. Re-order the campaign's channel list so the most responsive channel is tried first.
 *   3. The existing consent check in SendCampaignJob::handle() still applies —
 *      this class only changes the ORDER, not the eligibility gate.
 *
 * Responsiveness score for a channel =
 *   (click_count * 3 + open_count * 1) / max(delivery_count, 1)
 *
 * Falls back to static ChannelResolver ordering when there is no history.
 *
 * Override hook:
 *   If $campaign->force_channel is set, that channel is always placed first
 *   regardless of behaviour (e.g. transactional emails that must use email).
 */
final class SmartChannelResolver
{
    public function __construct(
        private readonly ChannelResolver            $staticResolver,
        private readonly CampaignDeliveryRepository $deliveryRepository,
        private readonly CampaignEventRepository    $eventRepository,
    )
    {
    }

    /**
     * Return channels in behaviour-optimised order for this member + campaign.
     *
     * @return CampaignChannel[]
     */
    public function resolveChannels(int $memberId, Campaign $campaign): array
    {
        $channels = $this->staticResolver->resolveChannels($campaign);

        if (empty($channels)) {
            return [];
        }

        // Hard override — admin has pinned a specific channel.
        if ($campaign->force_channel) {
            $forced = CampaignChannel::tryFrom($campaign->force_channel);
            if ($forced !== null) {
                return [$forced, ...array_filter($channels, fn($c) => $c !== $forced)];
            }
        }

        $scores = $this->buildChannelScores($memberId, $channels);

        // Sort channels by score descending; preserve original order as tie-break.
        usort($channels, function (CampaignChannel $a, CampaignChannel $b) use ($scores): int {
            return ($scores[$b->value] ?? 0.0) <=> ($scores[$a->value] ?? 0.0);
        });

        return $channels;
    }

    // -------------------------------------------------------------------------

    /**
     * @param CampaignChannel[] $channels
     * @return array<string, float>   channel value → score
     */
    private function buildChannelScores(int $memberId, array $channels): array
    {
        $scores = [];

        foreach ($channels as $channel) {
            $deliveries = $this->countDeliveries($memberId, $channel);
            $opens = $this->countEvents($memberId, $channel, 'open');
            $clicks = $this->countEvents($memberId, $channel, 'click');

            $scores[$channel->value] = $deliveries > 0
                ? ($clicks * 3 + $opens) / $deliveries
                : 0.0;
        }

        return $scores;
    }

    private function countDeliveries(int $memberId, CampaignChannel $channel): int
    {
        return \App\Models\CampaignDelivery::where('member_id', $memberId)
            ->where('channel', $channel->value)
            ->count();
    }

    private function countEvents(int $memberId, CampaignChannel $channel, string $type): int
    {
        // Events join back to deliveries via member_id + campaign_id.
        // We filter by channel via a sub-query on campaign_deliveries.
        return \App\Models\CampaignEvent::where('member_id', $memberId)
            ->where('event_type', $type)
            ->whereIn('campaign_id', function ($query) use ($memberId, $channel) {
                $query->select('campaign_id')
                    ->table('campaign_deliveries')
                    ->where('member_id', $memberId)
                    ->where('channel', $channel->value);
            })
            ->count();
    }
}
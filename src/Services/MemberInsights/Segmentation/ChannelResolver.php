<?php

namespace App\Services\MemberInsights\Segmentation;

use App\Enums\Member\CampaignChannel;
use App\Models\Campaign;

/**
 * Produces the ordered list of channels to attempt for a campaign.
 *
 * Primary channel is always first. Fallback channels follow in the
 * order defined on the campaign. The first channel that passes consent
 * is the one that gets used — later channels are never attempted.
 *
 * No DB access, no side effects. Pure data transformation.
 */
class ChannelResolver
{
    /**
     * @return CampaignChannel[]
     */
    public function resolveChannels(Campaign $campaign): array
    {
        $primary = $campaign->channel instanceof CampaignChannel
            ? $campaign->channel
            : CampaignChannel::tryFrom((string)$campaign->channel);

        if ($primary === null) {
            return [];
        }

        $channels = [$primary];

        $fallbackChannels = $campaign->fallback_channels;
        if (is_string($fallbackChannels)) {
            $fallbackChannels = json_decode($fallbackChannels, true);
        }

        foreach ($fallbackChannels ?? [] as $fallback) {
            $resolved = $fallback instanceof CampaignChannel
                ? $fallback
                : CampaignChannel::tryFrom((string)$fallback);

            if ($resolved !== null && $resolved !== $primary) {
                $channels[] = $resolved;
            }
        }

        return $channels;
    }
}
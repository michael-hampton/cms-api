<?php

namespace App\Tests\Unit\Services\Members\Segmentation;

use App\Enums\Member\CampaignChannel;
use App\Models\Campaign;
use App\Services\Members\Segmentation\ChannelResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

class ChannelResolverTest extends TestCase
{
    private ChannelResolver $resolver;

    public function test_returns_only_primary_channel_when_no_fallbacks_defined(): void
    {
        $campaign = $this->makeCampaign(CampaignChannel::EMAIL, null);

        $channels = $this->resolver->resolveChannels($campaign);

        $this->assertCount(1, $channels);
        $this->assertSame(CampaignChannel::EMAIL, $channels[0]);
    }

    private function makeCampaign(CampaignChannel $channel, ?array $fallbacks): Campaign
    {
        $campaign = Mockery::mock(Campaign::class)->makePartial();
        $campaign->channel = $channel->value;
        $campaign->fallback_channels = $fallbacks;
        return $campaign;
    }

    public function test_returns_primary_followed_by_fallback_channels(): void
    {
        $campaign = $this->makeCampaign(CampaignChannel::EMAIL, ['push', 'notification']);

        $channels = $this->resolver->resolveChannels($campaign);

        $this->assertCount(3, $channels);
        $this->assertSame(CampaignChannel::EMAIL, $channels[0]);
        $this->assertSame(CampaignChannel::PUSH, $channels[1]);
        $this->assertSame(CampaignChannel::NOTIFICATION, $channels[2]);
    }

    public function test_silently_ignores_invalid_fallback_channel_values(): void
    {
        $campaign = $this->makeCampaign(CampaignChannel::EMAIL, ['invalid_channel', 'push']);

        $channels = $this->resolver->resolveChannels($campaign);

        $this->assertCount(2, $channels);
        $this->assertSame(CampaignChannel::EMAIL, $channels[0]);
        $this->assertSame(CampaignChannel::PUSH, $channels[1]);
    }

    public function test_returns_primary_channel_when_fallback_channels_is_empty_array(): void
    {
        $campaign = $this->makeCampaign(CampaignChannel::PUSH, []);

        $channels = $this->resolver->resolveChannels($campaign);

        $this->assertCount(1, $channels);
        $this->assertSame(CampaignChannel::PUSH, $channels[0]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ChannelResolver();
    }
}
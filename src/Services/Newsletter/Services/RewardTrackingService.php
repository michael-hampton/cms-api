<?php

namespace App\Services\Newsletter\Services;

use App\Models\MemberReward;
use App\Services\Adverts\DealTrackingRecorder;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use Psr\Log\LoggerInterface;

class RewardTrackingService
{
    public function __construct(
        private readonly DealTrackingRecorder $trackingRecorder,
        private readonly LoggerInterface      $logger
    )
    {
    }

    public function trackRender(MemberReward $reward, ?int $dealId, NewsletterRenderContext $context): void
    {
        try {
            $advertContext = $context->toAdvertContext();

            $this->trackingRecorder->recordRewardRender(
                $reward->id,
                $dealId,
                $advertContext,
                $reward->site_id
            );
        } catch (\Exception $e) {
            // Tracking failures must not suppress rendering
            $this->logger->error('Failed to track reward render', [
                'reward_id' => $reward->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
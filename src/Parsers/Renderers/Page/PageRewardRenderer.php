<?php

namespace App\Parsers\Renderers\Page;

use App\Framework\Support\Logger;
use App\Repositories\Rewards\RewardsRepository;
use App\Services\Adverts\DealTrackingRecorder;
use App\Services\Adverts\RenderContext;
use App\Services\Adverts\RewardVisibilityResolver;

class PageRewardRenderer
{
    public function __construct(
        private readonly RewardsRepository        $rewardsRepository,
        private readonly RewardVisibilityResolver $visibilityResolver,
        private readonly DealTrackingRecorder     $trackingRecorder,
        private readonly Logger                   $logger,
    )
    {
    }

    public function render(int $rewardId, RenderContext $context, string $ip = '', string $userAgent = ''): string
    {
        try {
            if (!$context->memberId) {
                return '';
            }

            $reward = $this->rewardsRepository->findMemberRewardById($rewardId);

            if (!$reward) {
                return '';
            }

            $decision = $this->visibilityResolver->resolve($reward, $context);

            if (!$decision->shouldRender) {
                return '';
            }

            $this->trackingRecorder->recordRewardRender(
                rewardId: $rewardId,
                dealId: $decision->metadata['deal_id'] ?? null,
                context: $context,
                ip: $ip,
                userAgent: $userAgent,
            );

            return $this->renderHtml($reward, $decision->metadata, $context);

        } catch (\Exception $e) {
            $this->logger->error('PageRewardRenderer failed', [
                'reward_id' => $rewardId,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    private function renderHtml(object $reward, array $metadata, RenderContext $context): string
    {
        $name = htmlspecialchars($reward->rewardDefinition?->name ?? 'Member Reward');
        $description = htmlspecialchars($reward->rewardDefinition?->description ?? '');
        $voucher = htmlspecialchars($metadata['voucher_code'] ?? '');

        $clickUrl = $this->buildClickUrl('reward', $reward->id, $context);

        $html = '<div data-advert="reward" class="advert-block reward-block">';
        $html .= '<span class="advert-label reward-label">🎁 Member Reward</span>';
        $html .= '<div class="reward-content">';
        $html .= '<h3 class="reward-title">' . $name . '</h3>';

        if ($description) {
            $html .= '<p class="reward-description">' . $description . '</p>';
        }

        if ($voucher) {
            $html .= '<div class="reward-voucher">';
            $html .= '<span class="voucher-label">Your Code:</span>';
            $html .= '<span class="voucher-code">' . $voucher . '</span>';
            $html .= '<button class="voucher-copy-btn" onclick="navigator.clipboard.writeText(\'' . $voucher . '\')">Copy</button>';
            $html .= '</div>';
        }

        $html .= '<a href="' . $clickUrl . '" class="reward-cta btn-primary">View Reward</a>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    private function buildClickUrl(string $type, int $id, RenderContext $context): string
    {
        return url('/go/' . $type . '/' . $id) . '?' . http_build_query([
                'channel' => $context->channel,
                'surface_type' => $context->surfaceType,
                'surface_id' => $context->surfaceId,
            ]);
    }
}
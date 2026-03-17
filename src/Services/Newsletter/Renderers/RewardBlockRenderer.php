<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Logger;
use App\Framework\Support\Str;
use App\Repositories\Rewards\RewardsRepository;
use App\Services\Adverts\RenderContext;
use App\Services\Adverts\RewardVisibilityResolver;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\RewardBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;
use App\Services\Newsletter\Services\RewardTrackingService;
use App\Services\Newsletter\Services\TrackingUrlBuilder;
use Exception;

class RewardBlockRenderer implements EmailBlockRenderer
{
    public $type = 'reward';

    public function __construct(
        private readonly RewardsRepository        $rewardsRepository,
        private readonly RewardVisibilityResolver $eligibilityService,
        private readonly RewardTrackingService    $trackingService,
        private readonly TrackingUrlBuilder       $trackingUrlBuilder,
        private readonly Logger $logger
    )
    {
    }

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof RewardBlockData) {
            $this->logger->error('Invalid block data type for RewardBlockRenderer', [
                'expected' => RewardBlockData::class,
                'received' => get_class($blockData)
            ]);
            return RenderedBlock::skipped();
        }

        try {
            if (!$newsletterRenderContext->member) {
                $this->logger->info('Reward suppressed - no authenticated member');
                return RenderedBlock::skipped();
            }

            $reward = $this->rewardsRepository->findMemberRewardById($blockData->rewardId);

            if (!$reward) {
                $this->logger->warning('Reward not found', [
                    'reward_id' => $blockData->rewardId
                ]);
                return RenderedBlock::skipped();
            }

            $context = RenderContext::forNewsletter($newsletterRenderContext->newsletter->id, $newsletterRenderContext->member);

            $eligibility = $this->eligibilityService->resolve(
                $reward,
                $context
            );

            if (!$eligibility->shouldRender) {
                $this->logger->info('Reward suppressed', [
                    'reward_id' => $reward->id,
                    'reason' => $eligibility->reason
                ]);
                return RenderedBlock::skipped();
            }

            // Track AFTER eligibility check, BEFORE rendering
            $this->trackingService->trackRender($reward, $context->surfaceId, $newsletterRenderContext);

            $html = $this->renderHtml($reward, $eligibility, $newsletterRenderContext);
            return RenderedBlock::rendered($html);

        } catch (Exception $e) {
            $this->logger->error('Failed to render reward block', [
                'error' => $e->getMessage(),
                'reward_id' => $blockData->rewardId
            ]);
            return RenderedBlock::skipped();
        }
    }

    private function renderHtml($reward, $eligibility, NewsletterRenderContext $context): string
    {
        $viewRewardUrl = $this->trackingUrlBuilder->buildRewardUrl(
            $reward->id,
            $context->sendId,
            $context->includeTracking
        );

        $html = [];
        $html[] = '<div style="border: 2px solid #28a745; border-radius: 8px; padding: 20px; margin: 20px 0; background: #f0fff4;">';
        $html[] = '<div style="color: #28a745; font-size: 14px; font-weight: bold; margin-bottom: 10px;">🎁 Member Reward</div>';

        if ($reward->rewardDefinition) {
            $html[] = '<h3 style="margin: 0 0 10px 0; color: #28a745;">' . Str::sanitize($reward->rewardDefinition->name) . '</h3>';
            $html[] = '<p style="color: #666;">' . Str::sanitize($reward->rewardDefinition->description ?? '') . '</p>';
        }

        //$voucherCode = $eligibility->getMetadata('voucher_code');
        $voucherCode = null; //todo
        if ($voucherCode) {
            $html[] = '<div style="background: white; border: 2px dashed #28a745; padding: 15px; margin: 15px 0; text-align: center;">';
            $html[] = '<div style="color: #666; font-size: 12px; margin-bottom: 5px;">Your Code:</div>';
            $html[] = '<div style="font-size: 20px; font-weight: bold; font-family: monospace; color: #28a745;">' . Str::sanitize($voucherCode) . '</div>';
            $html[] = '</div>';
        }

        $html[] = '<a href="' . $viewRewardUrl . '" style="display: inline-block; padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; margin-top: 10px;">View Reward</a>';
        $html[] = '</div>';

        return implode("\n", $html);
    }
}
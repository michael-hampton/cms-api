<?php

namespace App\Services\Adverts;

use App\Framework\Database\Database;
use App\Framework\Support\SiteContext;
use App\Repositories\Offers\DealClickRepository;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Rewards\RewardsRepository;

class DealTrackingRecorder
{
    public function __construct(
        private readonly ProductOfferRepository $offerRepository,
        private readonly RewardsRepository      $rewardsRepository,
        private readonly DealClickRepository $dealClickRepository,
        private readonly Database               $database
    )
    {
    }

    public function recordOfferRender(int $offerId, ?int $dealId, RenderContext $context): void
    {
        $this->offerRepository->trackClick(
            $offerId,
            $context->memberId,
            'render',
            request()->ip(),
            request()->userAgent(),
            [
                'channel' => $context->channel,
                'surface_type' => $context->surfaceType,
                'surface_id' => $context->surfaceId,
                'deal_id' => $dealId,
            ]
        );
    }

    public function recordOfferClick(int $offerId, ?int $dealId, RenderContext $context): void
    {
        $this->offerRepository->trackClick(
            $offerId,
            $context->memberId,
            'click',
            request()->ip(),
            request()->userAgent(),
            [
                'channel' => $context->channel,
                'surface_type' => $context->surfaceType,
                'surface_id' => $context->surfaceId,
                'deal_id' => $dealId,
            ]
        );
    }

    public function recordRewardRender(int $rewardId, ?int $dealId, RenderContext $context, ?int $siteId = null): void
    {
        $siteId = $siteId ?? SiteContext::getId();

        $this->rewardsRepository->trackClick(
            $rewardId,
            $context->memberId,
            $siteId,
            'render',
            request()->ip(),
            request()->userAgent(),
            [
                'channel' => $context->channel,
                'surface_type' => $context->surfaceType,
                'surface_id' => $context->surfaceId,
                'deal_id' => $dealId,
            ]
        );
    }

    public function recordRewardClick(int $rewardId, ?int $dealId, RenderContext $context, ?int $siteId = null): void
    {
        $siteId = $siteId ?? SiteContext::getId();

        $this->rewardsRepository->trackClick(
            $rewardId,
            $context->memberId,
            $siteId,
            'click',
            request()->ip(),
            request()->userAgent(),
            [
                'channel' => $context->channel,
                'surface_type' => $context->surfaceType,
                'surface_id' => $context->surfaceId,
                'deal_id' => $dealId,
            ]
        );
    }

    public function recordRewardClaim(int $rewardId, int $memberId, ?int $siteId = null): bool
    {
        $siteId = $siteId ?? SiteContext::getId();

        return $this->database->transaction(function () use ($rewardId, $memberId, $siteId) {
            $reward = $this->rewardsRepository->findMemberRewardById($rewardId);

            if (!$reward || $reward->member_id !== $memberId) {
                return false;
            }

            if (!$reward->claim()) {
                return false;
            }

            $this->rewardsRepository->trackClick(
                $rewardId,
                $memberId,
                $siteId,
                'claim',
                request()->ip(),
                request()->userAgent()
            );

            return true;
        });
    }

    public function recordDealRender(int $productId, RenderContext $context, ?int $siteId = null): void
    {
        $siteId = $siteId ?? SiteContext::getId();

        $this->dealClickRepository->trackClick(
            $productId,
            $context->memberId,
            $siteId,
            'render',
            request()->ip(),
            request()->userAgent(),
            [
                'channel' => $context->channel,
                'surface_type' => $context->surfaceType,
                'surface_id' => $context->surfaceId,
            ]
        );
    }

    public function recordDealClick(int $productId, RenderContext $context, ?int $siteId = null): void
    {
        $siteId = $siteId ?? SiteContext::getId();

        $this->dealClickRepository->trackClick(
            $productId,
            $context->memberId,
            $siteId,
            'click',
            request()->ip(),
            request()->userAgent(),
            [
                'channel' => $context->channel,
                'surface_type' => $context->surfaceType,
                'surface_id' => $context->surfaceId,
            ]
        );
    }
}
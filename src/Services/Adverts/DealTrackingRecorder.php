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
        private readonly DealClickRepository  $dealClickRepository,
        private readonly TrackingDeduplicator $deduplicator,
        private readonly Database             $database,
    )
    {
    }

    public function recordOfferRender(
        int           $offerId,
        ?int          $dealId,
        RenderContext $context,
        string        $ip = '',
        string        $userAgent = '',
    ): void
    {
        if ($this->isDuplicateOffer($offerId, 'render', $context)) {
            return;
        }

        $this->offerRepository->trackClick(
            $offerId,
            $context->memberId,
            'render',
            $ip,
            $userAgent,
            [
                'channel' => $context->channel,
                'surface_type' => $context->surfaceType,
                'surface_id' => $context->surfaceId,
                'deal_id' => $dealId,
            ]
        );
    }

    public function recordOfferClick(
        int           $offerId,
        ?int          $dealId,
        RenderContext $context,
        string        $ip = '',
        string        $userAgent = '',
    ): void
    {
        if ($this->isDuplicateOffer($offerId, 'click', $context)) {
            return;
        }

        $this->offerRepository->trackClick(
            $offerId,
            $context->memberId,
            'click',
            $ip,
            $userAgent,
            [
                'channel' => $context->channel,
                'surface_type' => $context->surfaceType,
                'surface_id' => $context->surfaceId,
                'deal_id' => $dealId,
            ]
        );
    }

    public function recordRewardRender(
        int           $rewardId,
        ?int          $dealId,
        RenderContext $context,
        string        $ip = '',
        string        $userAgent = '',
        ?int          $siteId = null,
    ): void
    {
        if ($this->isDuplicateReward($rewardId, 'render', $context)) {
            return;
        }

        $siteId = $siteId ?? SiteContext::getId();

        $this->rewardsRepository->trackClick(
            $rewardId,
            $context->memberId,
            $siteId,
            'render',
            $ip,
            $userAgent,
            [
                'channel' => $context->channel,
                'surface_type' => $context->surfaceType,
                'surface_id' => $context->surfaceId,
                'deal_id' => $dealId,
            ]
        );
    }

    public function recordRewardClick(
        int           $rewardId,
        ?int          $dealId,
        RenderContext $context,
        string        $ip = '',
        string        $userAgent = '',
        ?int          $siteId = null,
    ): void
    {
        if ($this->isDuplicateReward($rewardId, 'click', $context)) {
            return;
        }

        $siteId = $siteId ?? SiteContext::getId();

        $this->rewardsRepository->trackClick(
            $rewardId,
            $context->memberId,
            $siteId,
            'click',
            $ip,
            $userAgent,
            [
                'channel' => $context->channel,
                'surface_type' => $context->surfaceType,
                'surface_id' => $context->surfaceId,
                'deal_id' => $dealId,
            ]
        );
    }

    public function recordRewardClaim(
        int    $rewardId,
        int    $memberId,
        string $ip = '',
        string $userAgent = '',
        ?int   $siteId = null,
    ): bool
    {
        $siteId = $siteId ?? SiteContext::getId();

        return $this->database->transaction(function () use ($rewardId, $memberId, $siteId, $ip, $userAgent) {
            $reward = $this->rewardsRepository->findMemberRewardById($rewardId);

            if (!$reward || $reward->member_id !== $memberId) {
                return false;
            }

            if (!$reward->claim()) {
                return false;
            }

            // Claims are not deduplicated — the claim() guard above
            // already prevents a reward being claimed more than once.
            $this->rewardsRepository->trackClick(
                $rewardId,
                $memberId,
                $siteId,
                'claim',
                $ip,
                $userAgent,
            );

            return true;
        });
    }

    public function recordDealRender(
        int           $productId,
        RenderContext $context,
        string        $ip = '',
        string        $userAgent = '',
        ?int          $siteId = null,
    ): void
    {
        if ($this->isDuplicateDeal($productId, 'render', $context)) {
            return;
        }

        $siteId = $siteId ?? SiteContext::getId();

        $this->dealClickRepository->trackClick(
            $productId,
            $context->memberId,
            $siteId,
            'render',
            $ip,
            $userAgent,
            [
                'channel' => $context->channel,
                'surface_type' => $context->surfaceType,
                'surface_id' => $context->surfaceId,
            ]
        );
    }

    public function recordDealClick(
        int           $productId,
        RenderContext $context,
        string        $ip = '',
        string        $userAgent = '',
        ?int          $siteId = null,
    ): void
    {
        if ($this->isDuplicateDeal($productId, 'click', $context)) {
            return;
        }

        $siteId = $siteId ?? SiteContext::getId();

        $this->dealClickRepository->trackClick(
            $productId,
            $context->memberId,
            $siteId,
            'click',
            $ip,
            $userAgent,
            [
                'channel' => $context->channel,
                'surface_type' => $context->surfaceType,
                'surface_id' => $context->surfaceId,
            ]
        );
    }

    // --- Dedup guards ---------------------------------------------------------
    // Guests (memberId = null) always return false — no dedup, always write.
    // Dedup key: memberId + entityId + action + surfaceType + surfaceId.
    // Renders and clicks are checked independently.

    private function isDuplicateOffer(int $offerId, string $action, RenderContext $context): bool
    {
        if ($context->memberId === null) {
            return false;
        }

        return $this->deduplicator->alreadyTrackedOffer(
            offerId: $offerId,
            memberId: $context->memberId,
            action: $action,
            surfaceType: $context->surfaceType,
            surfaceId: $context->surfaceId,
        );
    }

    private function isDuplicateDeal(int $productId, string $action, RenderContext $context): bool
    {
        if ($context->memberId === null) {
            return false;
        }

        return $this->deduplicator->alreadyTrackedDeal(
            productId: $productId,
            memberId: $context->memberId,
            action: $action,
            surfaceType: $context->surfaceType,
            surfaceId: $context->surfaceId,
        );
    }

    private function isDuplicateReward(int $rewardId, string $action, RenderContext $context): bool
    {
        if ($context->memberId === null) {
            return false;
        }

        return $this->deduplicator->alreadyTrackedReward(
            rewardId: $rewardId,
            memberId: $context->memberId,
            action: $action,
            surfaceType: $context->surfaceType,
            surfaceId: $context->surfaceId,
        );
    }
}
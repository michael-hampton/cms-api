<?php

namespace App\Services\Adverts;

use App\Repositories\Offers\DealClickRepository;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Rewards\RewardsRepository;

/**
 * Answers whether a tracking event has already been recorded
 * for a given member + entity + action + surface combination.
 *
 * Deduplication is all-time and per-action — renders and clicks
 * are deduplicated independently.
 *
 * Guests (memberId = null) are never deduplicated — callers must
 * check for null before calling this class.
 *
 * This class has one reason to change: the deduplication logic itself.
 * It does not know about writing or repositories beyond the read check.
 */
class TrackingDeduplicator
{
    public function __construct(
        private readonly ProductOfferRepository $offerRepository,
        private readonly DealClickRepository    $dealClickRepository,
        private readonly RewardsRepository      $rewardsRepository,
    )
    {
    }

    public function alreadyTrackedOffer(
        int    $offerId,
        int    $memberId,
        string $action,
        string $surfaceType,
        int    $surfaceId,
    ): bool
    {
        return $this->offerRepository->hasTracked(
            entityId: $offerId,
            memberId: $memberId,
            action: $action,
            surfaceType: $surfaceType,
            surfaceId: $surfaceId,
        );
    }

    public function alreadyTrackedDeal(
        int    $productId,
        int    $memberId,
        string $action,
        string $surfaceType,
        int    $surfaceId,
    ): bool
    {
        return $this->dealClickRepository->hasTracked(
            entityId: $productId,
            memberId: $memberId,
            action: $action,
            surfaceType: $surfaceType,
            surfaceId: $surfaceId,
        );
    }

    public function alreadyTrackedReward(
        int    $rewardId,
        int    $memberId,
        string $action,
        string $surfaceType,
        int    $surfaceId,
    ): bool
    {
        return $this->rewardsRepository->hasTracked(
            entityId: $rewardId,
            memberId: $memberId,
            action: $action,
            surfaceType: $surfaceType,
            surfaceId: $surfaceId,
        );
    }
}
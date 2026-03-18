<?php

declare(strict_types=1);

namespace App\Repositories\Alerts;

use App\Enums\Alerts\AlertableEntityType;
use App\Enums\Alerts\ExpiryAlertThreshold;
use App\Framework\Support\Collection;
use App\Models\GiftPromotion;
use App\Models\Member;
use App\Models\Merchant;
use App\Models\MerchantContact;
use App\Models\OfferExpiryAlert;
use App\Models\ProductOffer;
use App\Models\ProductOfferBundle;
use App\Models\ProductOfferBundleItem;
use App\Repositories\Repository;

/**
 * Handles all data access for offer expiry alert dispatch.
 *
 * This repository is the single data-access boundary for the
 * OfferExpiryAlertService. It owns:
 *  - Finding due entities per threshold
 *  - Recording sent alerts
 *  - Resolving merchant contacts (merchants have no email field directly)
 *  - Resolving members for each entity type
 *  - Resolving bundle merchants (bundles have no direct merchant_id)
 */
class OfferExpiryAlertRepository extends Repository
{
    // -------------------------------------------------------------------------
    // Finding due entities
    // -------------------------------------------------------------------------

    public function findDueProductOffers(ExpiryAlertThreshold $threshold): Collection
    {
        $window = $this->windowBounds($threshold);
        $alreadySent = $this->alreadySentIds(AlertableEntityType::ProductOffer, $threshold);

        return ProductOffer::query()
            ->where('is_active', true)
            ->whereBetween('end_date', [$window['from'], $window['to']])
            ->when($alreadySent->isNotEmpty(), fn($q) => $q->whereNotIn('id', $alreadySent->toArray()))
            ->with(['merchant'])
            ->get();
    }

    public function findDueProductOfferBundles(ExpiryAlertThreshold $threshold): Collection
    {
        $window = $this->windowBounds($threshold);
        $alreadySent = $this->alreadySentIds(AlertableEntityType::ProductOfferBundle, $threshold);

        return ProductOfferBundle::query()
            ->where('is_active', true)
            ->whereBetween('end_date', [$window['from'], $window['to']])
            ->when($alreadySent->isNotEmpty(), fn($q) => $q->whereNotIn('id', $alreadySent->toArray()))
            ->get();
    }

    public function findDueGiftPromotions(ExpiryAlertThreshold $threshold): Collection
    {
        $window = $this->windowBounds($threshold);
        $alreadySent = $this->alreadySentIds(AlertableEntityType::GiftPromotion, $threshold);

        return GiftPromotion::query()
            ->where('active', true)
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [$window['from'], $window['to']])
            ->when($alreadySent->isNotEmpty(), fn($q) => $q->whereNotIn('id', $alreadySent->toArray()))
            ->get();
    }

    // -------------------------------------------------------------------------
    // Merchant resolution
    // -------------------------------------------------------------------------

    /**
     * Resolves the primary MerchantContact for a merchant.
     *
     * Merchants have no email field — email is held on MerchantContact.
     * The primary contact is the first contact ordered by id ascending.
     * Returns null if the merchant has no contacts.
     */
    public function primaryContactForMerchant(int $merchantId): ?MerchantContact
    {
        return MerchantContact::query()
            ->where('merchant_id', $merchantId)
            ->orderBy('id')
            ->first();
    }

    /**
     * Finds a Merchant by id. Replaces the forbidden Merchant::find() static call.
     */
    public function findMerchant(int $merchantId): ?Merchant
    {
        return Merchant::query()->find($merchantId);
    }

    /**
     * Resolves the merchant for a bundle by inspecting the first bundle item's
     * offer merchant.
     *
     * Bundles have no direct merchant_id. We query the first active bundle item
     * that has an associated offer with a merchant. This is a DB query — it does
     * NOT call ->with() on an already-loaded Collection.
     */
    public function resolveBundleMerchant(int $bundleId): ?Merchant
    {
        $item = ProductOfferBundleItem::query()
            ->where('bundle_id', $bundleId)
            ->whereNotNull('product_offer_id')
            ->with(['productOffer.merchant'])
            ->first();

        return $item?->productOffer?->merchant ?? null;
    }

    // -------------------------------------------------------------------------
    // Member resolution
    // -------------------------------------------------------------------------

    /**
     * Finds Member models by a list of IDs.
     * Replaces the forbidden Member::whereIn() static call in the service.
     *
     * @param list<int> $memberIds
     */
    public function findMembersByIds(array $memberIds): Collection
    {
        if (empty($memberIds)) {
            return collect();
        }

        return Member::query()
            ->whereIn('id', $memberIds)
            ->get();
    }

    /**
     * Returns member IDs who wishlisted a given ProductOffer.
     *
     * @return list<int>
     */
    public function memberIdsWhoWishlistedOffer(int $offerId): array
    {
        return \App\Models\Wishlist::query()
            ->where('wishlistable_type', ProductOffer::class)
            ->where('product_id', $offerId)
            ->get()
            ->pluck('user_id')
            ->toArray();
    }

    /**
     * Returns member IDs who wishlisted a given ProductOfferBundle.
     *
     * @return list<int>
     */
    public function memberIdsWhoWishlistedBundle(int $bundleId): array
    {
        return \App\Models\Wishlist::query()
            ->where('wishlistable_type', ProductOfferBundle::class)
            ->where('product_id', $bundleId)
            ->get()
            ->pluck('member_id')
            ->toArray();
    }

    /**
     * Returns IDs of all active members who have placed at least one order
     * with the given merchant. Used for GiftPromotion member fan-out.
     *
     * @return list<int>
     */
    public function memberIdsForMerchant(int $merchantId): array
    {
        return \App\Models\Order::query()
            ->where('merchant_id', $merchantId)
            ->whereNotNull('member_id')
            ->whereHas('member', fn($q) => $q->where('is_active', true))
            ->distinct()
            ->get()
            ->pluck('member_id')
            ->toArray();
    }

    // -------------------------------------------------------------------------
    // Recording sent alerts
    // -------------------------------------------------------------------------

    public function record(
        AlertableEntityType  $entityType,
        int                  $entityId,
        ExpiryAlertThreshold $threshold,
    ): void
    {
        OfferExpiryAlert::create([
            'entity_type' => $entityType->value,
            'entity_id' => $entityId,
            'threshold_hours' => $threshold->value,
            'sent_at' => now(),
        ]);
    }

    public function hasBeenSent(
        AlertableEntityType  $entityType,
        int                  $entityId,
        ExpiryAlertThreshold $threshold,
    ): bool
    {
        return OfferExpiryAlert::query()
            ->where('entity_type', $entityType->value)
            ->where('entity_id', $entityId)
            ->where('threshold_hours', $threshold->value)
            ->exists();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** @return array{from: string, to: string} */
    private function windowBounds(ExpiryAlertThreshold $threshold): array
    {
        $now = now_datetime();

        return [
            'from' => $now->toDateTimeString(),
            'to' => $now->copy()->addHours($threshold->value)->toDateTimeString(),
        ];
    }

    private function alreadySentIds(AlertableEntityType $entityType, ExpiryAlertThreshold $threshold): Collection
    {
        return OfferExpiryAlert::query()
            ->where('entity_type', $entityType->value)
            ->where('threshold_hours', $threshold->value)
            ->get()
            ->pluck('entity_id');
    }

    protected function getModelClass(): string
    {
        return OfferExpiryAlert::class;
    }
}
<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use App\Enums\Alerts\AlertableEntityType;
use App\Enums\Alerts\ExpiryAlertThreshold;
use App\Events\Alerts\OfferExpiryAlertDispatched;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Mail\MailManager;
use App\Mail\Alerts\BundleExpiringSoonMemberMail;
use App\Mail\Alerts\BundleExpiringSoonMerchantMail;
use App\Mail\Alerts\GiftPromotionExpiringSoonMemberMail;
use App\Mail\Alerts\GiftPromotionExpiringSoonMerchantMail;
use App\Mail\Alerts\OfferExpiryAlertMerchantMail;
use App\Mail\Alerts\OfferExpiringSoonMerchantMail;
use App\Mail\Offers\OfferEndingSoon;
use App\Models\GiftPromotion;
use App\Models\Member;
use App\Models\ProductOffer;
use App\Models\ProductOfferBundle;
use App\Repositories\Alerts\OfferExpiryAlertRepository;

/**
 * Orchestrates expiry alert dispatch for ProductOffer, ProductOfferBundle,
 * and GiftPromotion across all configured thresholds.
 *
 * For each (entity, threshold) pair the service:
 *  1. Resolves the merchant's primary contact email via the repository.
 *  2. Sends a dedicated merchant alert mail.
 *  3. Sends member alert mails to relevant members.
 *  4. Records the alert in offer_expiry_alerts inside a transaction.
 *  5. Fires OfferExpiryAlertDispatched for audit logging.
 *
 * Member resolution per entity type:
 *  - ProductOffer       → members who wishlisted the offer
 *  - ProductOfferBundle → members who wishlisted the bundle
 *  - GiftPromotion      → all active members who have ordered from the merchant
 *
 * Merchant contact resolution:
 *  Merchants have no email field. All merchant mails are sent to the merchant's
 *  primary contact (MerchantContact). If no contact exists the alert is skipped.
 *
 * GiftPromotions with a null merchant_id are platform-wide — skipped entirely.
 *
 * The service does NOT catch mail exceptions; a failure propagates to the
 * command which records it per-entity and continues.
 */
final class OfferExpiryAlertService
{
    public function __construct(
        private readonly OfferExpiryAlertRepository $alertRepository,
        private readonly MailManager                $mailer,
        private readonly Database                   $database,
        private readonly EventDispatcher            $events,
    )
    {
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * @return array{processed: int, skipped: int}
     */
    public function processOffers(ExpiryAlertThreshold $threshold): array
    {
        $offers = $this->alertRepository->findDueProductOffers($threshold);
        $stats = ['processed' => 0, 'skipped' => 0];

        foreach ($offers as $offer) {
            if (!$offer->merchant) {
                $stats['skipped']++;
                continue;
            }

            $contact = $this->alertRepository->primaryContactForMerchant($offer->merchant->id);

            if (!$contact) {
                $stats['skipped']++;
                continue;
            }

            $this->mailer->to($contact->email)->send(new OfferExpiringSoonMerchantMail(
                merchant: $offer->merchant,
                offer: $offer,
                threshold: $threshold,
            ));

            $memberIds = $this->alertRepository->memberIdsWhoWishlistedOffer($offer->id);
            $members = $this->alertRepository->findMembersByIds($memberIds);
            $memberCount = $this->sendMemberAlertsForOffer($offer, $members, $threshold);

            $this->recordAndDispatch(
                entityType: AlertableEntityType::ProductOffer,
                entityId: $offer->id,
                threshold: $threshold,
                merchantCount: 1,
                memberCount: $memberCount,
            );

            $stats['processed']++;
        }

        return $stats;
    }

    /**
     * @return array{processed: int, skipped: int}
     */
    public function processBundles(ExpiryAlertThreshold $threshold): array
    {
        $bundles = $this->alertRepository->findDueProductOfferBundles($threshold);
        $stats = ['processed' => 0, 'skipped' => 0];

        foreach ($bundles as $bundle) {
            $merchantCount = 0;
            $merchant = $this->alertRepository->resolveBundleMerchant($bundle->id);

            if ($merchant) {
                $contact = $this->alertRepository->primaryContactForMerchant($merchant->id);

                if ($contact) {
                    $this->mailer->to($contact->email)->send(new BundleExpiringSoonMerchantMail(
                        merchant: $merchant,
                        bundle: $bundle,
                        threshold: $threshold,
                    ));
                    $merchantCount = 1;
                }
            }

            $memberIds = $this->alertRepository->memberIdsWhoWishlistedBundle($bundle->id);
            $members = $this->alertRepository->findMembersByIds($memberIds);
            $memberCount = $this->sendMemberAlertsForBundle($bundle, $members, $threshold);

            $this->recordAndDispatch(
                entityType: AlertableEntityType::ProductOfferBundle,
                entityId: $bundle->id,
                threshold: $threshold,
                merchantCount: $merchantCount,
                memberCount: $memberCount,
            );

            $stats['processed']++;
        }

        return $stats;
    }

    /**
     * @return array{processed: int, skipped: int}
     */
    public function processPromotions(ExpiryAlertThreshold $threshold): array
    {
        $promotions = $this->alertRepository->findDueGiftPromotions($threshold);
        $stats = ['processed' => 0, 'skipped' => 0];

        foreach ($promotions as $promotion) {
            if (!$promotion->merchant_id) {
                $stats['skipped']++;
                continue;
            }

            $merchant = $this->alertRepository->findMerchant($promotion->merchant_id);

            if (!$merchant) {
                $stats['skipped']++;
                continue;
            }

            $contact = $this->alertRepository->primaryContactForMerchant($merchant->id);

            if (!$contact) {
                $stats['skipped']++;
                continue;
            }

            $this->mailer->to($contact->email)->send(new GiftPromotionExpiringSoonMerchantMail(
                merchant: $merchant,
                promotion: $promotion,
                threshold: $threshold,
            ));

            $memberIds = $this->alertRepository->memberIdsForMerchant($promotion->merchant_id);
            $members = $this->alertRepository->findMembersByIds($memberIds);
            $memberCount = $this->sendMemberAlertsForPromotion($promotion, $members, $threshold);

            $this->recordAndDispatch(
                entityType: AlertableEntityType::GiftPromotion,
                entityId: $promotion->id,
                threshold: $threshold,
                merchantCount: 1,
                memberCount: $memberCount,
            );

            $stats['processed']++;
        }

        return $stats;
    }

    // -------------------------------------------------------------------------
    // Private: mail sending
    // -------------------------------------------------------------------------

    /** @param iterable<Member> $members */
    private function sendMemberAlertsForOffer(
        ProductOffer         $offer,
        iterable             $members,
        ExpiryAlertThreshold $threshold,
    ): int
    {
        $count = 0;

        foreach ($members as $member) {
            $this->mailer->to($member->email)->send(new OfferEndingSoon(
                member: $member,
                offer: $offer,
                hoursRemaining: $threshold->value,
            ));
            $count++;
        }

        return $count;
    }

    /** @param iterable<Member> $members */
    private function sendMemberAlertsForBundle(
        ProductOfferBundle   $bundle,
        iterable             $members,
        ExpiryAlertThreshold $threshold,
    ): int
    {
        $count = 0;

        foreach ($members as $member) {
            $this->mailer->to($member->email)->send(new BundleExpiringSoonMemberMail(
                member: $member,
                bundle: $bundle,
                threshold: $threshold,
            ));
            $count++;
        }

        return $count;
    }

    /** @param iterable<Member> $members */
    private function sendMemberAlertsForPromotion(
        GiftPromotion        $promotion,
        iterable             $members,
        ExpiryAlertThreshold $threshold,
    ): int
    {
        $count = 0;

        foreach ($members as $member) {
            $this->mailer->to($member->email)->send(new GiftPromotionExpiringSoonMemberMail(
                member: $member,
                promotion: $promotion,
                threshold: $threshold,
            ));
            $count++;
        }

        return $count;
    }

    // -------------------------------------------------------------------------
    // Private: recording + event
    // -------------------------------------------------------------------------

    private function recordAndDispatch(
        AlertableEntityType  $entityType,
        int                  $entityId,
        ExpiryAlertThreshold $threshold,
        int                  $merchantCount,
        int                  $memberCount,
    ): void
    {
        $this->database->transaction(function () use (
            $entityType, $entityId, $threshold, $merchantCount, $memberCount
        ) {
            $this->alertRepository->record($entityType, $entityId, $threshold);

            $this->events->dispatch(new OfferExpiryAlertDispatched(
                entityType: $entityType,
                entityId: $entityId,
                threshold: $threshold,
                merchantAlertsSent: $merchantCount,
                memberAlertsSent: $memberCount,
            ));
        });
    }
}
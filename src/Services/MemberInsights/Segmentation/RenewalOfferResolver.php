<?php

namespace App\Services\MemberInsights\Segmentation;

use App\Models\Segment;
use App\Models\Subscription;
use App\Models\SubscriptionPlanPricing;
use App\Models\Voucher;
use App\Repositories\MemberInsights\SubscriptionSegmentRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Repositories\Vouchers\VoucherRepository;

/**
 * Resolves the full renewal targeting context for a subscription.
 *
 * Given a subscription ID and optional filters, returns:
 *   - The subscription's current active segment (if any)
 *   - The best matching promotion for that segment
 *   - All available renewal offers, narrowed by the supplied filters
 *
 * This is a read-only service. Nothing is written.
 */
class RenewalOfferResolver
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SubscriptionSegmentRepository $subscriptionSegmentRepository,
        private readonly VoucherRepository $voucherRepository,
        private readonly SubscriptionPlanRepository $subscriptionPlanRepository,
    ) {
    }

    public function resolve(int $subscriptionId, RenewalOfferFilter $filter): array
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if ($subscription === null) {
            throw new \InvalidArgumentException("Subscription #{$subscriptionId} not found.");
        }

        $activeAssignment = $this->subscriptionSegmentRepository->findActive($subscriptionId);

        $segment = $activeAssignment?->segment;

        $voucher = $segment
            ? $this->voucherRepository->findBestForSubscriptionSegment(
                segment: $segment,
                planId: $subscription->plan_id,
                filter: $filter
            )
            : null;

        $offers = $this->subscriptionPlanRepository->findDiscountedPricingForPlan(
            planId: $subscription->plan_id,
            filter: $filter
        );

        return [
            'segment' => $segment ? $this->formatSegment($segment) : null,
            'promotion' => $voucher ? $this->formatVoucher($voucher) : null,
            'offers' => array_map(
                fn ($pricing) => $this->formatPricingOffer($pricing),
                $offers instanceof \App\Framework\Support\Collection
                    ? $offers->all()
                    : $offers
            ),
        ];
    }

    private function formatVoucher(Voucher $voucher): array
    {
        return [
            'id' => $voucher->id,
            'code' => $voucher->code,
            'name' => $voucher->name,
            'description' => $voucher->description,
            'discount_type' => $voucher->getStripeDiscountType(),
            'discount_amount' => $voucher->getStripeAmountOff(),
            'discount_percentage' => $voucher->getStripePercentOff(),
            'duration' => $voucher->getSubscriptionDiscountDuration(),
            'duration_months' => $voucher->getSubscriptionDurationMonths(),
        ];
    }

    private function formatPricingOffer(SubscriptionPlanPricing $pricing): array
    {
        $standardHasOffer = $pricing->sale_price !== null
            && $pricing->price !== null
            && $pricing->sale_price < $pricing->price;

        $digitalHasOffer = $pricing->digital_sale_price !== null
            && $pricing->digital_price !== null
            && $pricing->digital_sale_price < $pricing->digital_price;

        return [
            'id' => $pricing->id,
            'plan_id' => $pricing->plan_id,

            'edition' => $pricing->edition ?? null,
            'region' => $pricing->region ?? null,
            'payment_type' => $pricing->payment_type ?? null,

            'standard' => [
                'price' => $pricing->price,
                'sale_price' => $pricing->sale_price,
                'has_offer' => $standardHasOffer,
                'discount_amount' => $standardHasOffer
                    ? round($pricing->price - $pricing->sale_price, 2)
                    : 0,
                'discount_percentage' => $standardHasOffer
                    ? round((($pricing->price - $pricing->sale_price) / $pricing->price) * 100, 2)
                    : 0,
            ],

            'digital' => [
                'price' => $pricing->digital_price,
                'sale_price' => $pricing->digital_sale_price,
                'has_offer' => $digitalHasOffer,
                'discount_amount' => $digitalHasOffer
                    ? round($pricing->digital_price - $pricing->digital_sale_price, 2)
                    : 0,
                'discount_percentage' => $digitalHasOffer
                    ? round((($pricing->digital_price - $pricing->digital_sale_price) / $pricing->digital_price) * 100, 2)
                    : 0,
            ],
        ];
    }

    private function formatSegment(Segment $segment): array
    {
        return [
            'id' => $segment->id,
            'key' => $segment->key,
            'name' => $segment->name,
            'description' => $segment->description,
            'category' => $segment->category,
            'subject_type' => $segment->subject_type,
            'priority' => $segment->priority,
        ];
    }
}
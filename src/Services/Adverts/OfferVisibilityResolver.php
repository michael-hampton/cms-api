<?php

namespace App\Services\Adverts;

use App\Enums\Adverts\SuppressionReason;
use App\Models\ProductOffer;
use App\Repositories\Offers\ProductOfferRepository;

class OfferVisibilityResolver
{
    public function __construct(
        public readonly ProductOfferRepository  $offerRepository,
        private readonly EligibilityRuleFactory $ruleFactory
    )
    {
    }

    public function resolveMultiple(array $offerIds, RenderContext $context): array
    {
        $offers = $this->offerRepository->findMany($offerIds);
        $decisions = [];

        foreach ($offers as $offer) {
            $decision = $this->resolve($offer, $context);
            if ($decision->shouldRender) {
                $decisions[] = [
                    'offer' => $offer,
                    'decision' => $decision,
                ];
            }
        }

        return $decisions;
    }

    public function resolve(ProductOffer $offer, RenderContext $context): VisibilityDecision
    {
        // Check temporal validity
        if (!$offer->is_active) {
            return VisibilityDecision::hide(SuppressionReason::OFFER_INACTIVE);
        }

        if ($offer->start_date && $offer->start_date > $context->timestamp) {
            return VisibilityDecision::hide(SuppressionReason::OFFER_NOT_STARTED);
        }

        if ($offer->end_date && $offer->end_date < $context->timestamp) {
            return VisibilityDecision::hide(SuppressionReason::OFFER_EXPIRED);
        }

        // Check eligibility rules
        $member = $context->memberId ? \App\Models\Member::find($context->memberId) : null;

        $eligibilityRules = $offer->eligibility_rules ?? [];
        if (!empty($eligibilityRules)) {
            $rules = $this->ruleFactory->createFromArray($eligibilityRules);

            foreach ($rules as $rule) {
                $decision = $rule->evaluate($member);
                if (!$decision->shouldRender) {
                    return $decision;
                }
            }
        }

        return VisibilityDecision::show([
            'offer_id' => $offer->id,
            'offer_name' => $offer->name ?? 'Special Offer',
            'deal_id' => $offer->deal_id,
        ]);
    }
}
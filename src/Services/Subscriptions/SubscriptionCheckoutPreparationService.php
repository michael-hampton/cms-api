<?php

namespace App\Services\Subscriptions;

use App\DTO\Subscriptions\ResolvedSubscriptionPrice;
use App\Enums\Subscriptions\SubscriptionType;
use App\Models\SubscriptionPlan;
use App\Repositories\Billing\PaymentMethodRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Subscriptions\Calculators\SubscriptionPricingResolver;
use Exception;

class SubscriptionCheckoutPreparationService
{
    public function __construct(
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly SubscriptionEligibilityService $eligibilityService,
        private readonly SubscriptionPricingResolver $pricingResolver,
    ) {
    }

    public function prepare(int $memberId, array $data, int $siteId): SubscriptionCheckoutPreparationResult
    {
        $plan = $this->resolveValidPlan($data['subscription_plan_id']);
        $paymentMethod = $this->resolveValidPaymentMethod($data['payment_method']);
        $this->assertMemberEligibility($memberId, $plan->id, $siteId);
        $resolvedPrice = $this->resolveSubscriptionPrice($plan, $data, $memberId);

        return new SubscriptionCheckoutPreparationResult(
            plan: $plan,
            paymentMethod: $paymentMethod,
            resolvedPrice: $resolvedPrice,
        );
    }

    private function resolveValidPlan(int $planId): SubscriptionPlan
    {
        $plan = $this->planRepository->find($planId);

        if (!$plan || !$plan->is_active) {
            throw new Exception('Invalid subscription plan');
        }

        return $plan;
    }

    private function resolveValidPaymentMethod(string $paymentMethodCode): object
    {
        $paymentMethod = $this->paymentMethodRepository->findByCode($paymentMethodCode);

        if (!$paymentMethod || !$paymentMethod->is_active) {
            throw new Exception('Invalid payment method');
        }

        return $paymentMethod;
    }

    private function assertMemberEligibility(int $memberId, int $planId, int $siteId): void
    {
        $eligibility = $this->eligibilityService->canMemberSubscribe($memberId, $planId, $siteId, true);

        if (!$eligibility['can_subscribe']) {
            throw new Exception($eligibility['reason']);
        }
    }

    private function resolveSubscriptionPrice(SubscriptionPlan $plan, array $data, int $memberId): ResolvedSubscriptionPrice
    {
        return $this->pricingResolver->resolve($plan, [
            'variant' => $data['variant'] ?? SubscriptionType::DIGITAL->value,
            'pricing_tier_id' => $data['pricing_tier_id'] ?? null,
            'voucher_code' => $data['voucher_code'] ?? null,
        ], $memberId);
    }
}

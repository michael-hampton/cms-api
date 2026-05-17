<?php

namespace App\Services\Subscriptions;

use App\DTO\Stripe\CreateStripeSubscriptionDto;
use App\DTO\Stripe\CreateStripeSubscriptionScheduleDto;
use App\DTO\Stripe\StripeSubscriptionResultDto;
use App\Enums\Subscriptions\SubscriptionStrategyType;
use App\Framework\Database\Database;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Billing\Stripe\Contracts\StripeSubscriptionGatewayInterface;
use App\Services\Billing\Stripe\Contracts\StripeSubscriptionScheduleGatewayInterface;
use App\Services\Billing\Stripe\StripeCustomerGateway;
use App\Services\Billing\Stripe\StripeSubscriptionGateway;
use App\Services\Billing\Stripe\StripeSubscriptionScheduleGateway;
use App\Services\Billing\Stripe\SubscriptionPricingStrategyResolver;

class SubscriptionBillingService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly StripePaymentProcessor $stripeProcessor,
        private readonly Database               $database,
        private readonly SubscriptionPricingStrategyResolver       $strategyResolver,
        //private readonly StripeCustomerGateway        $subscriptionGateway,
        private readonly StripeSubscriptionScheduleGateway $scheduleGateway,
        private readonly StripeSubscriptionGateway $subscriptionGateway,
    )
    {
    }

    /**
     * Update billing date for a subscription
     */
    public function updateBillingDate(
        int  $subscriptionId,
        int  $dayOfMonth,
        bool $prorate = true
    ): array
    {
        return $this->database->transaction(function () use ($subscriptionId, $dayOfMonth, $prorate) {
            $subscription = $this->subscriptionRepository->find($subscriptionId);

            if (!$subscription) {
                throw new \Exception('Subscription not found');
            }

            if (!$subscription->hasStripeSubscription()) {
                throw new \Exception('Can only update billing date for Stripe subscriptions');
            }

            if ($subscription->status !== 'active') {
                throw new \Exception('Can only update billing date for active subscriptions');
            }

            // Validate day of month
            if ($dayOfMonth < 1 || $dayOfMonth > 31) {
                throw new \Exception('Day must be between 1 and 31');
            }

            // Update in Stripe
            $result = $this->stripeProcessor->updateBillingCycleAnchor(
                $subscription->getStripeSubscriptionId(),
                $dayOfMonth,
                $prorate
            );

            if (!$result['success']) {
                throw new \Exception($result['message'] ?? 'Failed to update billing date');
            }

            // Update local subscription record
            $newBillingDate = new \DateTime($result['new_billing_date']);

            $this->subscriptionRepository->update($subscriptionId, [
                'next_billing_date' => $newBillingDate->format('Y-m-d H:i:s'),
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'billing_day_of_month' => $dayOfMonth,
                    'last_billing_update' => date('Y-m-d H:i:s')
                ])
            ]);

            return [
                'success' => true,
                'new_billing_date' => $result['new_billing_date'],
                'message' => 'Billing date updated successfully'
            ];
        });
    }

    /**
     * Preview billing date change
     */
    public function previewBillingDateChange(int $subscriptionId, int $dayOfMonth): array
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            return [
                'success' => false,
                'message' => 'Subscription not found'
            ];
        }

        if (!$subscription->hasStripeSubscription()) {
            return [
                'success' => false,
                'message' => 'Can only preview billing date changes for Stripe subscriptions'
            ];
        }

        return $this->stripeProcessor->calculateBillingDateProration(
            $subscription->getStripeSubscriptionId(),
            $dayOfMonth
        );
    }

    /**
     * Create a Stripe subscription for the given local subscription record.
     *
     * The pricing tier must already have its stripe_price_id (and
     * stripe_intro_price_id when applicable) populated — run
     * sync:stripe-prices before calling this.
     *
     * @throws \RuntimeException  When required Stripe price IDs are missing.
     * @throws \RuntimeException  When the Stripe API call fails.
     */
    public function createSubscription(
        Subscription            $subscription,
        SubscriptionPlan        $plan,
        SubscriptionPlanPricing $pricingTier,
        string                  $stripeCustomerId,
    ): StripeSubscriptionResultDto {
        $strategy = $this->strategyResolver->resolve($pricingTier);

        return match ($strategy->type) {
            SubscriptionStrategyType::STANDARD    => $this->createStandard($subscription, $pricingTier, $stripeCustomerId),
            SubscriptionStrategyType::TRIAL       => $this->createTrial($subscription, $pricingTier, $stripeCustomerId, $strategy->trialDays),
            SubscriptionStrategyType::INTRO,
            SubscriptionStrategyType::TRIAL_INTRO => $this->createScheduled($subscription, $pricingTier, $stripeCustomerId, $strategy->trialDays),
        };
    }

    // ── Private dispatch methods ─────────────────────────────────────────────

    private function createStandard(
        Subscription            $subscription,
        SubscriptionPlanPricing $pricingTier,
        string                  $stripeCustomerId,
    ): StripeSubscriptionResultDto {
        $priceId = $this->requireStripePriceId($pricingTier);

        return $this->subscriptionGateway->create(
            new CreateStripeSubscriptionDto(
                stripeCustomerId: $stripeCustomerId,
                stripePriceId:    $priceId,
                subscriptionId:   $subscription->id,
                planId:           $subscription->plan_id,
                memberId:         $subscription->member_id,
                siteId:           $subscription->site_id,
            )
        );
    }

    private function createTrial(
        Subscription            $subscription,
        SubscriptionPlanPricing $pricingTier,
        string                  $stripeCustomerId,
        int                     $trialDays,
    ): StripeSubscriptionResultDto {
        $priceId = $this->requireStripePriceId($pricingTier);

        return $this->subscriptionGateway->createWithTrial(
            new CreateStripeSubscriptionDto(
                stripeCustomerId: $stripeCustomerId,
                stripePriceId:    $priceId,
                subscriptionId:   $subscription->id,
                planId:           $subscription->plan_id,
                memberId:         $subscription->member_id,
                siteId:           $subscription->site_id,
                trialDays:        $trialDays,
            )
        );
    }

    private function createScheduled(
        Subscription            $subscription,
        SubscriptionPlanPricing $pricingTier,
        string                  $stripeCustomerId,
        ?int                    $trialDays,
    ): StripeSubscriptionResultDto {
        $recurringPriceId = $this->requireStripePriceId($pricingTier);
        $introPriceId     = $this->requireStripeIntroPriceId($pricingTier);

        return $this->scheduleGateway->create(
            new CreateStripeSubscriptionScheduleDto(
                stripeCustomerId:  $stripeCustomerId,
                introPriceId:      $introPriceId,
                recurringPriceId:  $recurringPriceId,
                introCycles:       $pricingTier->intro_cycles,
                subscriptionId:    $subscription->id,
                planId:            $subscription->plan_id,
                memberId:          $subscription->member_id,
                siteId:            $subscription->site_id,
                trialDays:         $trialDays,
            )
        );
    }

    // ── Guards ───────────────────────────────────────────────────────────────

    private function requireStripePriceId(SubscriptionPlanPricing $pricingTier): string
    {
        if (empty($pricingTier->stripe_price_id)) {
            throw new \RuntimeException(
                "Pricing tier #{$pricingTier->id} has no stripe_price_id. Run sync:stripe-prices first."
            );
        }

        return $pricingTier->stripe_price_id;
    }

    private function requireStripeIntroPriceId(SubscriptionPlanPricing $pricingTier): string
    {
        if (empty($pricingTier->stripe_intro_price_id)) {
            throw new \RuntimeException(
                "Pricing tier #{$pricingTier->id} has no stripe_intro_price_id. Run sync:stripe-prices first."
            );
        }

        return $pricingTier->stripe_intro_price_id;
    }

}
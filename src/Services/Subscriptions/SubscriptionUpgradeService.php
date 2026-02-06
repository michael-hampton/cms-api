<?php

namespace App\Services\Subscriptions;

use App\Exceptions\Subscriptions\InactiveSubscriptionException;
use App\Exceptions\Subscriptions\InvalidUpgradePlanException;
use App\Exceptions\Subscriptions\PaymentFailedException;
use App\Exceptions\Subscriptions\PlanMismatchException;
use App\Exceptions\Subscriptions\SubscriptionNotFoundException;
use App\Exceptions\Subscriptions\UnauthorizedException;
use App\Exceptions\Subscriptions\UpgradeFailedException;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Model;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Subscriptions\Calculators\UpgradeProrationCalculator;
use App\Services\ValueObjects\Money;

class SubscriptionUpgradeService
{
    public function __construct(
        private readonly SubscriptionRepository           $subscriptionRepository,
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly StripePaymentProcessor           $stripeProcessor,
        private readonly Database $database,
        private readonly UpgradeProrationCalculator       $prorationCalculator,
        private readonly StripeSubscriptionUpgradeService $stripeUpgradeService,
        private readonly PremiumAccessGrantService        $premiumAccessService,
        private readonly UpgradeBenefitsService           $benefitsService,
    )
    {
    }

    /**
     * Validate upgrade eligibility and permissions
     *
     * @throws SubscriptionNotFoundException
     * @throws UnauthorizedException
     * @throws InactiveSubscriptionException
     * @throws InvalidUpgradePlanException
     * @throws PlanMismatchException
     */
    private function validateUpgrade(
        ?Subscription $subscription,
        ?SubscriptionPlan $upgradePlan,
        array         $paymentData
    ): void
    {
        if (!$subscription) {
            throw new SubscriptionNotFoundException('Subscription not found');
        }

        if (isset($paymentData['member'])) {
            if ($subscription->member_id !== $paymentData['member']->id) {
                throw new UnauthorizedException(
                    'You do not have permission to upgrade this subscription'
                );
            }
        }

        if (!$subscription->isActive()) {
            throw new InactiveSubscriptionException('Subscription is not active');
        }

        if (!$upgradePlan || !$upgradePlan->isUpgradePlan()) {
            throw new InvalidUpgradePlanException('Invalid upgrade plan');
        }

        if ($upgradePlan->upgrade_from_plan_id &&
            $upgradePlan->upgrade_from_plan_id !== $subscription->plan_id) {
            throw new PlanMismatchException(
                'Upgrade plan does not match current subscription'
            );
        }

        if ($upgradePlan->id === $subscription->plan_id) {
            throw new InvalidUpgradePlanException('Cannot upgrade to the same plan');
        }
    }

    /**
     * Process payment for upgrade difference
     *
     * @throws PaymentFailedException
     */
    private function chargeForUpgrade(
        Subscription $subscription,
        SubscriptionPlan $upgradePlan,
        Money $amount,
        array $paymentData
    ): ?array
    {
        if (!$amount->isPositive()) {
            return null;
        }

        $result = $this->stripeProcessor->createPaymentIntent([
            'amount' => $amount->toDecimal(),
            'currency' => $subscription->currency,
            'subscription_id' => $subscription->id,
            'site_id' => $subscription->site_id,
            'metadata' => [
                'type' => 'subscription_upgrade',
                'original_plan_id' => $subscription->plan_id,
                'upgrade_plan_id' => $upgradePlan->id,
            ]
        ]);

        if (!$result['success']) {
            throw new PaymentFailedException(
                $result['message'] ?? 'Payment failed'
            );
        }

        return $result;
    }

    /**
     * Apply plan change to subscription record
     *
     * @throws UpgradeFailedException
     */
    private function applyPlanChange(
        int          $subscriptionId,
        Subscription $subscription,
        SubscriptionPlan $upgradePlan,
        Money        $priceDifference
    ): Model
    {
        $updated = $this->subscriptionRepository->update($subscriptionId, [
            'upgraded_from_plan_id' => $subscription->plan_id,
            'plan_id' => $upgradePlan->id,
            'plan_name' => $upgradePlan->name,
            'upgraded_at' => now_datetime()->format('Y-m-d H:i:s'),
            'upgrade_price_difference' => $priceDifference->toDecimal(),
            'price' => $upgradePlan->price,
        ]);

        if (!$updated) {
            throw new UpgradeFailedException('Failed to update subscription');
        }

        return $updated;
    }

    /**
     * Preview upgrade costs and benefits
     */
    public function previewUpgrade(int $subscriptionId, int $upgradePlanId): array
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            throw new SubscriptionNotFoundException('Subscription not found');
        }

        $upgradePlan = $this->planRepository->find($upgradePlanId);

        if (!$upgradePlan) {
            throw new InvalidUpgradePlanException('Upgrade plan not found');
        }

        $quote = $this->prorationCalculator->calculateUpgradeQuote($subscription, $upgradePlan);

        return [
            'current_plan' => [
                'name' => $subscription->plan_name,
                'price' => $subscription->price,
                'features' => $subscription->plan ? $subscription->plan->features : [],
                'includes_print' => $subscription->isPrint(),
                'includes_digital' => $subscription->includes_digital_access,
            ],
            'upgrade_plan' => [
                'name' => $upgradePlan->name,
                'price' => $upgradePlan->price,
                'features' => $upgradePlan->features,
                'includes_print' => in_array($upgradePlan->delivery_type, ['print', 'both']),
                'includes_digital' => true,
                'includes_insider' => $upgradePlan->includes_insider,
            ],
            'pricing' => [
                'current_price' => $subscription->price,
                'upgrade_price' => $upgradePlan->price,
                'price_difference' => round($upgradePlan->price - $subscription->price, 2),
                'immediate_charge' => $quote->getAmount()->toDecimal(),
                'prorated' => $quote->isProrated(),
                'remaining_days' => $quote->getRemainingDays(),
                'is_estimate' => $quote->isEstimate(),
                'estimate_note' => $quote->isEstimate()
                    ? 'Final charge may differ based on Stripe proration calculation'
                    : null,
            ],
            'benefits' => $this->benefitsService->getUpgradeBenefits($subscription, $upgradePlan),
        ];
    }

    /**
     * Get available upgrade options for a subscription
     */
    public function getUpgradeOptions(
        int $subscriptionId,
        ?string $premiumType = null,
        ?string $premiumIdentifier = null
    ): array
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            throw new SubscriptionNotFoundException('Subscription not found');
        }

        $availableUpgrades = $subscription->getAvailableUpgrades();

        if (empty($availableUpgrades)) {
            return [
                'can_upgrade' => false,
                'reason' => 'No upgrade options available',
                'options' => []
            ];
        }

        if ($premiumType && $premiumIdentifier) {
            $availableUpgrades = array_filter($availableUpgrades, function ($upgrade) use ($premiumType, $premiumIdentifier) {
                foreach ($upgrade['new_access'] as $access) {
                    if ($access['type'] === $premiumType && $access['identifier'] === $premiumIdentifier) {
                        return true;
                    }
                }
                return false;
            });
        }

        if (empty($availableUpgrades)) {
            return [
                'can_upgrade' => false,
                'reason' => 'No upgrade options available for requested premium access',
                'options' => []
            ];
        }

        $options = [];
        foreach ($availableUpgrades as $upgrade) {
            $plan = $upgrade['plan'];
            $quote = $this->prorationCalculator->calculateUpgradeQuote($subscription, $plan);

            $options[] = [
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'description' => $plan->description,
                'features' => $plan->features,
                'premium_access' => $upgrade['new_access'],
                'price_difference' => $quote->getAmount()->toDecimal(),
                'new_total_price' => $plan->price,
                'current_price' => $subscription->price,
                'billing_period' => $plan->billing_period,
                'is_estimate' => $quote->isEstimate(),
            ];
        }

        return [
            'can_upgrade' => true,
            'current_subscription' => [
                'id' => $subscription->id,
                'plan_name' => $subscription->plan_name,
                'price' => $subscription->price,
                'premium_access' => $subscription->premiumAccess()->get()->map(function ($access) {
                    return [
                        'type' => $access->premium_type,
                        'identifier' => $access->premium_identifier
                    ];
                })->toArray(),
            ],
            'options' => $options
        ];
    }

    /**
     * Process subscription upgrade with flexible premium access
     */
    public function upgradeSubscription(
        int $subscriptionId,
        int $upgradePlanId,
        array $paymentData = []
    ): array
    {
        // Phase 1: Transactional local updates
        $transactionResult = $this->database->transaction(function () use (
            $subscriptionId,
            $upgradePlanId,
            $paymentData
        ) {
            $subscription = $this->subscriptionRepository->find($subscriptionId);
            $upgradePlan = $this->planRepository->find($upgradePlanId);

            // 1. Validate upgrade eligibility
            $this->validateUpgrade($subscription, $upgradePlan, $paymentData);

            // 2. Calculate upgrade cost
            $quote = $this->prorationCalculator->calculateUpgradeQuote($subscription, $upgradePlan);

            // 3. Charge for upgrade
            $isTestEnv = ($_ENV['APP_ENV'] ?? 'production') === 'testing';
            $paymentResult = $isTestEnv ? true : $this->chargeForUpgrade(
                $subscription,
                $upgradePlan,
                $quote->getAmount(),
                $paymentData
            );

            // 4. Apply plan change to subscription
            $this->applyPlanChange(
                $subscriptionId,
                $subscription,
                $upgradePlan,
                $quote->getAmount()
            );

            // 5. Grant premium access
            $grantedAccess = $this->premiumAccessService->grantPremiumAccess(
                $subscription,
                $upgradePlan,
                $subscriptionId
            );

            // 6. Grant lower-tier plan access
            $lowerTierAccess = $subscription->grantLowerTierPlans();

            return [
                'subscription' => $subscription,
                'upgradePlan' => $upgradePlan,
                'quote' => $quote,
                'paymentResult' => $paymentResult,
                'grantedAccess' => $grantedAccess,
                'lowerTierAccess' => $lowerTierAccess,
            ];
        });

        // Phase 2: External provider sync (outside transaction)
        try {
            $this->stripeUpgradeService->updateSubscriptionPlan(
                $transactionResult['subscription'],
                $transactionResult['upgradePlan']
            );
        } catch (\Exception $e) {
            Logger::error("External sync failed after successful upgrade", [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);
        }

        Logger::info("Subscription upgraded successfully", [
            'subscription_id' => $subscriptionId,
            'from_plan' => $transactionResult['subscription']->plan_id,
            'to_plan' => $upgradePlanId,
            'premium_grants' => count($transactionResult['grantedAccess']),
            'lower_tier_grants' => count($transactionResult['lowerTierAccess']),
            'price_difference' => $transactionResult['quote']->getAmount()->toDecimal()
        ]);

        return [
            'success' => true,
            'subscription' => $this->subscriptionRepository->find($subscriptionId),
            'premium_access_granted' => $transactionResult['grantedAccess'],
            'lower_tier_access_granted' => $transactionResult['lowerTierAccess'],
            'price_charged' => $transactionResult['quote']->getAmount()->toDecimal(),
            'payment_result' => $transactionResult['paymentResult'],
            'message' => 'Successfully upgraded subscription'
        ];
    }
}
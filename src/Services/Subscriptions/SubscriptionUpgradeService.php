<?php

namespace App\Services\Subscriptions;

use App\DTO\Stripe\CreatePaymentIntentDto;
use App\DTO\Stripe\PaymentIntentResultDto;
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
use App\Services\Billing\Stripe\Contracts\StripePaymentIntentGatewayInterface;
use App\Services\Subscriptions\Calculators\UpgradeProrationCalculator;
use App\Services\ValueObjects\Money;

class SubscriptionUpgradeService
{
    private const COMPLETED_PAYMENT_STATUSES = ['succeeded'];

    public function __construct(
        private readonly SubscriptionRepository              $subscriptionRepository,
        private readonly SubscriptionPlanRepository          $planRepository,
        private readonly StripePaymentIntentGatewayInterface $paymentIntentGateway,
        private readonly Database                            $database,
        private readonly UpgradeProrationCalculator          $prorationCalculator,
        private readonly StripeSubscriptionUpgradeService    $stripeUpgradeService,
        private readonly PremiumAccessGrantService           $premiumAccessService,
        private readonly UpgradeBenefitsService              $benefitsService,
    ) {
    }

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
                'id' => $upgradePlan->id,
                'name' => $upgradePlan->name,
                'price' => $upgradePlan->price,
                'features' => $upgradePlan->features,
                'includes_print' => $upgradePlan->hasPrintOption(),
                'includes_digital' => $upgradePlan->hasDigitalOption(),
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

    public function getUpgradeOptions(
        int $subscriptionId,
        ?string $premiumType = null,
        ?string $premiumIdentifier = null,
    ): array {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            throw new SubscriptionNotFoundException('Subscription not found');
        }

        $availableUpgrades = $subscription->getAvailableUpgrades();

        if (empty($availableUpgrades)) {
            return [
                'can_upgrade' => false,
                'reason' => 'No upgrade options available',
                'options' => [],
            ];
        }

        if ($premiumType && $premiumIdentifier) {
            $availableUpgrades = array_filter(
                $availableUpgrades,
                static function (array $upgrade) use ($premiumType, $premiumIdentifier): bool {
                    foreach ($upgrade['new_access'] as $access) {
                        if ($access['type'] === $premiumType
                            && $access['identifier'] === $premiumIdentifier) {
                            return true;
                        }
                    }

                    return false;
                },
            );
        }

        if (empty($availableUpgrades)) {
            return [
                'can_upgrade' => false,
                'reason' => 'No upgrade options available for requested premium access',
                'options' => [],
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
                'premium_access' => $subscription->premiumAccess()->get()->map(
                    static fn ($access): array => [
                        'type' => $access->premium_type,
                        'identifier' => $access->premium_identifier,
                    ],
                )->toArray(),
            ],
            'options' => $options,
        ];
    }

    public function upgradeSubscription(
        int $subscriptionId,
        int $upgradePlanId,
        array $paymentData = [],
    ): array {
        $transactionResult = $this->database->transaction(function () use (
            $subscriptionId,
            $upgradePlanId,
            $paymentData,
        ): array {
            $subscription = $this->subscriptionRepository->find($subscriptionId);
            $upgradePlan = $this->planRepository->find($upgradePlanId);
            $this->validateUpgrade($subscription, $upgradePlan, $paymentData);

            $quote = $this->prorationCalculator->calculateUpgradeQuote($subscription, $upgradePlan);
            $paymentResult = $this->resolveUpgradePayment(
                $subscription,
                $upgradePlan,
                $quote->getAmount(),
                $paymentData,
            );

            if ($paymentResult instanceof PaymentIntentResultDto
                && !$this->isCompletedPayment($paymentResult)) {
                if (!$paymentResult->success || !$paymentResult->clientSecret) {
                    throw new PaymentFailedException(
                        $paymentResult->errorMessage ?? 'Payment could not be prepared.',
                    );
                }

                return [
                    'requires_confirmation' => true,
                    'subscription' => $subscription,
                    'upgradePlan' => $upgradePlan,
                    'quote' => $quote,
                    'paymentResult' => $paymentResult,
                    'grantedAccess' => [],
                    'lowerTierAccess' => [],
                ];
            }

            $this->applyPlanChange(
                $subscriptionId,
                $subscription,
                $upgradePlan,
                $quote->getAmount(),
            );

            $grantedAccess = $this->premiumAccessService->grantPremiumAccess(
                $subscription,
                $upgradePlan,
                $subscriptionId,
            );
            $lowerTierAccess = $subscription->grantLowerTierPlans();

            return [
                'requires_confirmation' => false,
                'subscription' => $subscription,
                'upgradePlan' => $upgradePlan,
                'quote' => $quote,
                'paymentResult' => $paymentResult,
                'grantedAccess' => $grantedAccess,
                'lowerTierAccess' => $lowerTierAccess,
            ];
        });

        if ($transactionResult['requires_confirmation']) {
            return [
                'success' => true,
                'requires_confirmation' => true,
                'subscription' => $transactionResult['subscription'],
                'price_charged' => $transactionResult['quote']->getAmount()->toDecimal(),
                'payment_result' => $transactionResult['paymentResult']->toLegacyArray(),
                'message' => 'Payment authentication is required before the upgrade can be applied.',
            ];
        }

        try {
            $this->stripeUpgradeService->updateSubscriptionPlan(
                $transactionResult['subscription'],
                $transactionResult['upgradePlan'],
            );
        } catch (\Exception $e) {
            Logger::error('External sync failed after successful upgrade', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
        }

        Logger::info('Subscription upgraded successfully', [
            'subscription_id' => $subscriptionId,
            'from_plan' => $transactionResult['subscription']->plan_id,
            'to_plan' => $upgradePlanId,
            'premium_grants' => count($transactionResult['grantedAccess']),
            'lower_tier_grants' => count($transactionResult['lowerTierAccess']),
            'price_difference' => $transactionResult['quote']->getAmount()->toDecimal(),
        ]);

        return [
            'success' => true,
            'requires_confirmation' => false,
            'subscription' => $this->subscriptionRepository->find($subscriptionId),
            'premium_access_granted' => $transactionResult['grantedAccess'],
            'lower_tier_access_granted' => $transactionResult['lowerTierAccess'],
            'price_charged' => $transactionResult['quote']->getAmount()->toDecimal(),
            'payment_result' => $transactionResult['paymentResult'] instanceof PaymentIntentResultDto
                ? $transactionResult['paymentResult']->toLegacyArray()
                : $transactionResult['paymentResult'],
            'message' => 'Successfully upgraded subscription',
        ];
    }

    private function resolveUpgradePayment(
        Subscription $subscription,
        SubscriptionPlan $upgradePlan,
        Money $amount,
        array $paymentData,
    ): PaymentIntentResultDto|bool|null {
        if (!$amount->isPositive()) {
            return null;
        }

        if (($_ENV['APP_ENV'] ?? 'production') === 'testing') {
            return true;
        }

        $paymentIntentId = trim((string) ($paymentData['payment_intent_id'] ?? ''));
        if ($paymentIntentId !== '') {
            $result = $this->paymentIntentGateway->retrieve($paymentIntentId);
            $this->validateCompletedPayment($result, $amount, $subscription);

            return $result;
        }

        return $this->createUpgradePaymentIntent($subscription, $upgradePlan, $amount);
    }

    private function createUpgradePaymentIntent(
        Subscription $subscription,
        SubscriptionPlan $upgradePlan,
        Money $amount,
    ): PaymentIntentResultDto {
        $result = $this->paymentIntentGateway->create(new CreatePaymentIntentDto(
            amountCents: $amount->toCents(),
            currency: $subscription->currency,
            metadata: [
                'type' => 'subscription_upgrade',
                'original_plan_id' => $subscription->plan_id,
                'upgrade_plan_id' => $upgradePlan->id,
                'subscription_id' => $subscription->id,
                'site_id' => $subscription->site_id,
            ],
        ));

        if (!$result->success) {
            throw new PaymentFailedException($result->errorMessage ?? 'Payment failed');
        }

        return $result;
    }

    private function validateCompletedPayment(
        PaymentIntentResultDto $result,
        Money $amount,
        Subscription $subscription,
    ): void {
        if (!$result->success || !$this->isCompletedPayment($result)) {
            throw new PaymentFailedException('Payment has not completed successfully.');
        }

        if ($result->amountCents !== null && $result->amountCents !== $amount->toCents()) {
            throw new PaymentFailedException('Payment amount does not match the upgrade quote.');
        }

        if ($result->currency !== null
            && strtolower($result->currency) !== strtolower((string) $subscription->currency)) {
            throw new PaymentFailedException('Payment currency does not match the subscription.');
        }
    }

    private function isCompletedPayment(PaymentIntentResultDto $result): bool
    {
        if ($result->status === null) {
            return $result->success && !$result->requiresAction();
        }

        return in_array($result->status, self::COMPLETED_PAYMENT_STATUSES, true);
    }

    private function validateUpgrade(
        ?Subscription $subscription,
        ?SubscriptionPlan $upgradePlan,
        array $paymentData,
    ): void {
        if (!$subscription) {
            throw new SubscriptionNotFoundException('Subscription not found');
        }

        if (isset($paymentData['member'])
            && $subscription->member_id !== $paymentData['member']->id) {
            throw new UnauthorizedException(
                'You do not have permission to upgrade this subscription',
            );
        }

        if (!$subscription->isActive()) {
            throw new InactiveSubscriptionException('Subscription is not active');
        }

        if (!$upgradePlan || !$upgradePlan->isUpgradePlan()) {
            throw new InvalidUpgradePlanException('Invalid upgrade plan');
        }

        if ($upgradePlan->upgrade_from_plan_id
            && $upgradePlan->upgrade_from_plan_id !== $subscription->plan_id) {
            throw new PlanMismatchException(
                'Upgrade plan does not match current subscription',
            );
        }

        if ($upgradePlan->id === $subscription->plan_id) {
            throw new InvalidUpgradePlanException('Cannot upgrade to the same plan');
        }
    }

    private function applyPlanChange(
        int $subscriptionId,
        Subscription $subscription,
        SubscriptionPlan $upgradePlan,
        Money $priceDifference,
    ): Model {
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
}

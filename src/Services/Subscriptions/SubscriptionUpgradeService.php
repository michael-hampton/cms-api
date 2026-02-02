<?php

namespace App\Services\Subscriptions;

use App\Exceptions\Subscriptions\InactiveSubscriptionException;
use App\Exceptions\Subscriptions\InvalidUpgradePlanException;
use App\Exceptions\Subscriptions\MissingStripePriceException;
use App\Exceptions\Subscriptions\PaymentFailedException;
use App\Exceptions\Subscriptions\PlanMismatchException;
use App\Exceptions\Subscriptions\StripeUpdateFailedException;
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
use App\Services\Subscriptions\ValueObjects\Money;
use App\Services\Subscriptions\ValueObjects\UpgradeQuote;

class SubscriptionUpgradeService
{
    private readonly array $benefitMap;
    public function __construct(
        private readonly SubscriptionRepository     $subscriptionRepository,
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly StripePaymentProcessor     $stripeProcessor,
        private readonly Database $database,
        array                     $benefitMap = []
    )
    {
        $this->benefitMap = !empty($benefitMap) ? $benefitMap : $this->getDefaultBenefitMap();
    }

    /**
     * Calculate upgrade quote with proration
     * NOTE: For Stripe subscriptions, this is an ESTIMATE only.
     * Actual charges may differ based on Stripe's proration calculation.
     */
    private function calculateUpgradeQuote(
        Subscription     $subscription,
        SubscriptionPlan $upgradePlan
    ): UpgradeQuote
    {
        $currentPrice = Money::fromDecimal($subscription->price, $subscription->currency);
        $upgradePrice = Money::fromDecimal($upgradePlan->price, $subscription->currency);
        $priceDifference = $upgradePrice->subtract($currentPrice);

        $remainingDays = null;
        $isProrated = false;

        // Calculate proration for recurring Stripe subscriptions
        if ($subscription->hasStripeSubscription() && $subscription->next_billing_date) {
            $now = new \DateTime();
            $nextBilling = $subscription->next_billing_date;

            $totalDays = $subscription->start_date->diff($nextBilling)->days;
            $remainingDays = $now->diff($nextBilling)->days;

            if ($totalDays > 0 && $remainingDays > 0) {
                // Prorated difference based on remaining time
                $prorationFactor = $remainingDays / $totalDays;
                $priceDifference = $priceDifference->multiply($prorationFactor);
                $isProrated = true;
            }
        }

        $finalAmount = $priceDifference->isPositive()
            ? $priceDifference
            : Money::fromCents(0, $subscription->currency);

        // Mark as estimate for Stripe subscriptions since Stripe calculates independently
        $isEstimate = $subscription->hasStripeSubscription();

        return new UpgradeQuote($finalAmount, $isProrated, $remainingDays, $isEstimate);
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
        ?Subscription     $subscription,
        ?SubscriptionPlan $upgradePlan,
        array             $paymentData
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
    }

    /**
     * Process payment for upgrade difference
     *
     * @throws PaymentFailedException
     */
    private function chargeForUpgrade(
        Subscription $subscription,
        SubscriptionPlan $upgradePlan,
        Money        $amount,
        array        $paymentData
    ): ?array
    {
        if (!$amount->isPositive()) {
            return null;
        }

        // Create payment intent for the upgrade difference
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
        int              $subscriptionId,
        Subscription     $subscription,
        SubscriptionPlan $upgradePlan,
        Money            $priceDifference
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
     * Grant premium access for upgraded plan
     */
    private function grantPremiumAccess(
        Subscription     $subscription,
        SubscriptionPlan $upgradePlan,
        int              $subscriptionId
    ): array
    {
        $premiumGrants = $upgradePlan->getPremiumAccessGrants();
        $grantedAccess = [];

        foreach ($premiumGrants as $grant) {
            $access = $subscription->grantPremiumAccess(
                $grant['type'],
                $grant['identifier'],
                $grant['expires_at'] ?? null
            );
            $grantedAccess[] = $access;

            // Backward compatibility: Set digital access flag for insider
            if ($grant['type'] === 'newsletter' && $grant['identifier'] === 'insider') {
                $this->subscriptionRepository->update($subscriptionId, [
                    'includes_digital_access' => true
                ]);
            }
        }

        return $grantedAccess;
    }

    /**
     * Update Stripe subscription to new plan
     *
     * @throws MissingStripePriceException
     * @throws StripeUpdateFailedException
     */
    private function updateStripeSubscription(
        Subscription     $subscription,
        SubscriptionPlan $upgradePlan
    ): void
    {
        $stripeSubscriptionId = $subscription->getStripeSubscriptionId();

        if (!$stripeSubscriptionId || $_ENV['APP_ENV'] === 'testing') {
            return; // No Stripe subscription, nothing to update
        }

        // Fail fast if Stripe price ID is missing
        $priceId = $upgradePlan->stripe_price_id;
        if (!$priceId) {
            throw new MissingStripePriceException(
                "Cannot upgrade: Plan '{$upgradePlan->name}' is missing Stripe price ID. " .
                "Please contact support."
            );
        }

        // Delegate to payment processor for all Stripe operations
        $result = $this->stripeProcessor->updateSubscriptionPlan(
            $stripeSubscriptionId,
            $priceId,
            [
                'upgraded_at' => now_datetime()->format('Y-m-d H:i:s'),
                'original_plan_id' => $subscription->plan_id,
            ]
        );

        if (!$result['success']) {
            throw new StripeUpdateFailedException(
                "Failed to update Stripe subscription: " . ($result['error'] ?? 'Unknown error')
            );
        }

        Logger::info("Stripe subscription updated for upgrade", [
            'subscription_id' => $subscription->id,
            'stripe_subscription_id' => $stripeSubscriptionId
        ]);
    }

    /**
     * Sync changes to external payment providers
     */
    private function syncExternalProviders(
        Subscription     $subscription,
        SubscriptionPlan $upgradePlan
    ): void
    {
        if ($subscription->hasStripeSubscription()) {
            $this->updateStripeSubscription($subscription, $upgradePlan);
        }
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

        $quote = $this->calculateUpgradeQuote($subscription, $upgradePlan);

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
            'benefits' => $this->getUpgradeBenefits($subscription, $upgradePlan),
        ];
    }

    /**
     * Get available upgrade options for a subscription
     */
    public function getUpgradeOptions(
        int     $subscriptionId,
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

        // Filter by specific premium type if requested
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
            $quote = $this->calculateUpgradeQuote($subscription, $plan);

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
        int   $subscriptionId,
        int   $upgradePlanId,
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
            $quote = $this->calculateUpgradeQuote($subscription, $upgradePlan);

            // 3. Charge for upgrade
            $paymentResult = $_ENV['APP_ENV'] === 'testing' ? true : $this->chargeForUpgrade(
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
            $grantedAccess = $this->grantPremiumAccess(
                $subscription,
                $upgradePlan,
                $subscriptionId
            );

            // 6. Grant lower-tier plan access (explicit method call)
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
            $this->syncExternalProviders(
                $transactionResult['subscription'],
                $transactionResult['upgradePlan']
            );
        } catch (MissingStripePriceException|StripeUpdateFailedException $e) {
            // Rethrow critical errors that should fail the upgrade
            throw $e;
        } catch (\Exception $e) {
            // Log non-critical sync errors but don't fail - local state is committed
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

    /**
     * Get upgrade benefits comparing access levels
     */
    private function getUpgradeBenefits(Subscription $subscription, SubscriptionPlan $upgradePlan): array
    {
        $benefits = [];

        $currentAccess = $subscription->premiumAccess();
        $newAccess = $upgradePlan->getPremiumAccessGrants();

        $currentAccessKeys = $currentAccess->map(
            fn($a) => $a->premium_type . ':' . $a->premium_identifier
        )->toArray();

        foreach ($newAccess as $access) {
            $key = $access['type'] . ':' . $access['identifier'];

            if (!in_array($key, $currentAccessKeys)) {
                $benefits[] = $this->getBenefitForAccess($access['type'], $access['identifier']);
            }
        }

        // Compare features
        $currentFeatures = $subscription->plan ? $subscription->plan->features : [];
        $upgradeFeatures = $upgradePlan->features;

        $newFeatures = array_diff($upgradeFeatures ?? [], $currentFeatures ?? []);

        foreach (array_slice($newFeatures, 0, 3) as $feature) {
            $benefits[] = [
                'icon' => '✨',
                'title' => 'New Feature',
                'description' => $feature
            ];
        }

        return $benefits;
    }

    /**
     * Get benefit description for premium access type from configuration
     */
    private function getBenefitForAccess(string $type, string $identifier): array
    {
        $key = $type . ':' . $identifier;

        // Use configured benefit map with fallback
        $benefitMap = !empty($this->benefitMap) ? $this->benefitMap : config('subscription_benefits', []);

        return $benefitMap[$key] ?? [
            'icon' => '⭐',
            'title' => ucfirst($identifier),
            'description' => 'Premium ' . $type . ' access'
        ];
    }

    private function getDefaultBenefitMap(): array
    {
        return [
            'newsletter:insider' => [
                'icon' => '🔓',
                'title' => 'Unlock Insider Newsletter',
                'description' => 'Immediate access to all premium Insider articles and features'
            ],
            'newsletter:tech-weekly' => [
                'icon' => '💻',
                'title' => 'Tech Weekly Newsletter',
                'description' => 'Weekly technology insights and analysis'
            ],
            'newsletter:business-brief' => [
                'icon' => '📊',
                'title' => 'Business Brief Newsletter',
                'description' => 'Daily business news and market updates'
            ],
            'newsletter:politics-daily' => [
                'icon' => '🏛️',
                'title' => 'Politics Daily Newsletter',
                'description' => 'In-depth political coverage and analysis'
            ],
            'newsletter:sports-insider' => [
                'icon' => '⚽',
                'title' => 'Sports Insider Newsletter',
                'description' => 'Exclusive sports news and behind-the-scenes content'
            ],
            'archive:full' => [
                'icon' => '📚',
                'title' => 'Full Archive Access',
                'description' => 'Access our complete digital archive of past issues'
            ],
            'archive:recent' => [
                'icon' => '📰',
                'title' => 'Recent Archive Access',
                'description' => 'Access to articles from the past 12 months'
            ],
            'video:premium' => [
                'icon' => '🎥',
                'title' => 'Premium Video Content',
                'description' => 'Exclusive video interviews and documentaries'
            ],
            'video:live-events' => [
                'icon' => '📺',
                'title' => 'Live Event Streaming',
                'description' => 'Watch live coverage of exclusive events'
            ],
            'podcast:premium' => [
                'icon' => '🎙️',
                'title' => 'Premium Podcasts',
                'description' => 'Ad-free listening and exclusive bonus episodes'
            ],
            'community:forum' => [
                'icon' => '💬',
                'title' => 'Community Forum Access',
                'description' => 'Join discussions with other subscribers'
            ],
            'events:in-person' => [
                'icon' => '🎫',
                'title' => 'In-Person Events',
                'description' => 'Invitations to exclusive subscriber events'
            ],
            'events:virtual' => [
                'icon' => '🖥️',
                'title' => 'Virtual Events',
                'description' => 'Access to virtual Q&A sessions and webinars'
            ],
        ];
    }
}
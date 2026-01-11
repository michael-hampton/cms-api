<?php

namespace App\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Payment\StripePaymentProcessor;
use Exception;

class SubscriptionUpgradeService
{
    public function __construct(
        private readonly SubscriptionRepository     $subscriptionRepository,
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly StripePaymentProcessor     $stripeProcessor,
        private readonly Database                   $database
    )
    {
    }

    /**
     * Calculate prorated upgrade price
     */
    private function calculateUpgradePrice(Subscription $subscription, SubscriptionPlan $upgradePlan): float
    {
        $priceDifference = $upgradePlan->price - $subscription->price;

        // If subscription has Stripe and is recurring, calculate proration
        if ($subscription->hasStripeSubscription() && $subscription->next_billing_date) {
            $now = new \DateTime();
            $nextBilling = $subscription->next_billing_date;

            $totalDays = $subscription->start_date->diff($nextBilling)->days;
            $remainingDays = $now->diff($nextBilling)->days;

            if ($totalDays > 0 && $remainingDays > 0) {
                // Prorated difference based on remaining time
                $priceDifference = ($priceDifference / $totalDays) * $remainingDays;
            }
        }

        return max(0, $priceDifference);
    }

    /**
     * Process payment for upgrade
     */
    private function processUpgradePayment(
        Subscription     $subscription,
        SubscriptionPlan $upgradePlan,
        float            $amount,
        array            $paymentData
    ): array
    {
        if ($amount <= 0) {
            return ['success' => true, 'message' => 'No payment required'];
        }

        // Create payment intent for the upgrade difference
        $result = $this->stripeProcessor->createPaymentIntent([
            'amount' => $amount,
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
            throw new Exception($result['message'] ?? 'Failed to create payment');
        }

        return $result;
    }

    /**
     * Update Stripe subscription to new plan
     */
    private function updateStripeSubscription(Subscription $subscription, SubscriptionPlan $upgradePlan): void
    {
        try {
            $stripeSubscriptionId = $subscription->getStripeSubscriptionId();

            if (!$stripeSubscriptionId) {
                return;
            }

            // Get or create price for upgrade plan
            $priceId = $upgradePlan->stripe_price_id;

            if (!$priceId) {
                Logger::warning("Upgrade plan missing Stripe price ID", [
                    'plan_id' => $upgradePlan->id
                ]);
                return;
            }

            // Update Stripe subscription to use new price
            $stripe = new \Stripe\StripeClient($_ENV['STRIPE_SECRET_KEY'] ?? config('payment.stripe.secret_key'));

            $stripeSubscription = $stripe->subscriptions->retrieve($stripeSubscriptionId);

            $stripe->subscriptions->update($stripeSubscriptionId, [
                'items' => [
                    [
                        'id' => $stripeSubscription->items->data[0]->id,
                        'price' => $priceId,
                    ]
                ],
                'proration_behavior' => 'always_invoice', // Charge immediately for upgrade
                'metadata' => [
                    'upgraded_at' => now_datetime()->format('Y-m-d H:i:s'),
                    'original_plan_id' => $subscription->upgraded_from_plan_id,
                ]
            ]);

            Logger::info("Stripe subscription updated for upgrade", [
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $stripeSubscriptionId
            ]);

        } catch (\Exception $e) {
            Logger::error("Failed to update Stripe subscription for upgrade", [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);

            // Don't throw - the local subscription is already upgraded
        }
    }

    /**
     * Preview upgrade costs and benefits
     */
    public function previewUpgrade(int $subscriptionId, int $upgradePlanId): array
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            throw new Exception('Subscription not found');
        }

        $upgradePlan = $this->planRepository->find($upgradePlanId);

        if (!$upgradePlan) {
            throw new Exception('Upgrade plan not found');
        }

        $priceDifference = $this->calculateUpgradePrice($subscription, $upgradePlan);

        $remainingDays = null;
        if ($subscription->next_billing_date) {
            $now = new \DateTime();
            $remainingDays = $now->diff($subscription->next_billing_date)->days;
        }

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
                'immediate_charge' => $priceDifference,
                'prorated' => $priceDifference < ($upgradePlan->price - $subscription->price),
                'remaining_days' => $remainingDays,
            ],
            'benefits' => $this->getUpgradeBenefits($subscription, $upgradePlan),
        ];
    }

    /**
     * Get available upgrade options for a subscription
     */
    public function getUpgradeOptions(int $subscriptionId, ?string $premiumType = null, ?string $premiumIdentifier = null): array
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            throw new Exception('Subscription not found');
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
            $priceDifference = $this->calculateUpgradePrice($subscription, $plan);

            $options[] = [
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'description' => $plan->description,
                'features' => $plan->features,
                'premium_access' => $upgrade['new_access'],
                'price_difference' => round($priceDifference, 2),
                'new_total_price' => $plan->price,
                'current_price' => $subscription->price,
                'billing_period' => $plan->billing_period,
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
        return $this->database->transaction(function () use ($subscriptionId, $upgradePlanId, $paymentData) {
            $subscription = $this->subscriptionRepository->find($subscriptionId);

            if (!$subscription) {
                throw new Exception('Subscription not found');
            }

            if (isset($paymentData['member'])) {
                if ($subscription->member_id !== $paymentData['member']->id) {
                    throw new Exception('You do not have permission to upgrade this subscription');
                }
            }

            if (!$subscription->isActive()) {
                throw new Exception('Subscription is not active');
            }

            $upgradePlan = $this->planRepository->find($upgradePlanId);

            if (!$upgradePlan || !$upgradePlan->isUpgradePlan()) {
                throw new Exception('Invalid upgrade plan');
            }

            // Verify the upgrade plan is compatible
            if ($upgradePlan->upgrade_from_plan_id && $upgradePlan->upgrade_from_plan_id !== $subscription->plan_id) {
                throw new Exception('Upgrade plan does not match current subscription');
            }

            $priceDifference = $this->calculateUpgradePrice($subscription, $upgradePlan);

            // Process payment for upgrade if there's a price difference
            $paymentResult = null;
            if ($priceDifference > 0) {
                $paymentResult = $this->processUpgradePayment(
                    $subscription,
                    $upgradePlan,
                    $priceDifference,
                    $paymentData
                );

                if (!$paymentResult['success']) {
                    throw new Exception($paymentResult['message'] ?? 'Payment failed');
                }
            }

            // Update subscription
            $updated = $this->subscriptionRepository->update($subscriptionId, [
                'upgraded_from_plan_id' => $subscription->plan_id,
                'plan_id' => $upgradePlanId,
                'plan_name' => $upgradePlan->name,
                'upgraded_at' => now_datetime()->format('Y-m-d H:i:s'),
                'upgrade_price_difference' => $priceDifference,
                'price' => $upgradePlan->price,
            ]);

            if (!$updated) {
                throw new Exception('Failed to update subscription');
            }

            // Grant all premium access from the new plan
            $premiumGrants = $upgradePlan->getPremiumAccessGrants();
            $grantedAccess = [];

            foreach ($premiumGrants as $grant) {
                $access = $subscription->grantPremiumAccess(
                    $grant['type'],
                    $grant['identifier'],
                    $grant['expires_at'] ?? null
                );
                $grantedAccess[] = $access;

                // Backward compatibility
                if ($grant['type'] === 'newsletter' && $grant['identifier'] === 'insider') {
                    $this->subscriptionRepository->update($subscriptionId, [
                        'includes_digital_access' => true
                    ]);
                }
            }

            // Update Stripe subscription if exists
            if ($subscription->hasStripeSubscription()) {
                $this->updateStripeSubscription($subscription, $upgradePlan);
            }

            Logger::info("Subscription upgraded with premium access", [
                'subscription_id' => $subscriptionId,
                'from_plan' => $subscription->plan_id,
                'to_plan' => $upgradePlanId,
                'premium_grants' => $premiumGrants,
                'price_difference' => $priceDifference
            ]);

            return [
                'success' => true,
                'subscription' => $this->subscriptionRepository->find($subscriptionId),
                'premium_access_granted' => $grantedAccess,
                'price_charged' => round($priceDifference, 2),
                'payment_result' => $paymentResult,
                'message' => 'Successfully upgraded subscription'
            ];
        });
    }

    /**
     * Get upgrade benefits comparing access levels
     */
    private function getUpgradeBenefits(Subscription $subscription, SubscriptionPlan $upgradePlan): array
    {
        $benefits = [];

        $currentAccess = $subscription->premiumAccess();

        $newAccess = $upgradePlan->getPremiumAccessGrants();

        $currentAccessKeys = $currentAccess->map(fn($a) => $a->premium_type . ':' . $a->premium_identifier)->toArray();

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
     * Get benefit description for premium access type
     */
    private function getBenefitForAccess(string $type, string $identifier): array
    {
        // This could be pulled from a config or database
        $benefitMap = [
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
            'archive:full' => [
                'icon' => '📚',
                'title' => 'Full Archive Access',
                'description' => 'Access our complete digital archive of past issues'
            ],
            'video:premium' => [
                'icon' => '🎥',
                'title' => 'Premium Video Content',
                'description' => 'Exclusive video interviews and documentaries'
            ],
        ];

        $key = $type . ':' . $identifier;

        return $benefitMap[$key] ?? [
            'icon' => '⭐',
            'title' => ucfirst($identifier),
            'description' => 'Premium ' . $type . ' access'
        ];
    }
}
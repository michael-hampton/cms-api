<?php

namespace App\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\SubscriptionRepository;
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
     * Get available upgrade options for a subscription
     */
    public function getUpgradeOptions(int $subscriptionId): array
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            throw new Exception('Subscription not found');
        }

        if (!$subscription->canUpgradeToInsider()) {
            return [
                'can_upgrade' => false,
                'reason' => 'Subscription is not eligible for upgrade',
                'options' => []
            ];
        }

        // Find upgrade plans for current plan
        $upgradePlans = $this->planRepository->getUpgradePlansFor($subscription->plan_id);

        if ($upgradePlans->isEmpty()) {
            return [
                'can_upgrade' => false,
                'reason' => 'No upgrade options available',
                'options' => []
            ];
        }

        $options = [];
        foreach ($upgradePlans as $plan) {
            $priceDifference = $this->calculateUpgradePrice($subscription, $plan);

            $options[] = [
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'description' => $plan->description,
                'features' => $plan->features,
                'includes_insider' => $plan->includes_insider,
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
                'includes_digital' => $subscription->includes_digital_access,
            ],
            'options' => $options
        ];
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
     * Process subscription upgrade
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

            if (!$subscription->canUpgradeToInsider()) {
                throw new Exception('Subscription is not eligible for upgrade');
            }

            $upgradePlan = $this->planRepository->find($upgradePlanId);

            if (!$upgradePlan || !$upgradePlan->isUpgradePlan()) {
                throw new Exception('Invalid upgrade plan');
            }

            // Verify the upgrade plan is for this subscription's plan
            if ($upgradePlan->upgrade_from_plan_id !== $subscription->plan_id) {
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
                'includes_digital_access' => true,
                'upgraded_at' => now_datetime()->format('Y-m-d H:i:s'),
                'upgrade_price_difference' => $priceDifference,
                'price' => $upgradePlan->price,
            ]);

            if (!$updated) {
                throw new Exception('Failed to update subscription');
            }

            // Update Stripe subscription if exists
            if ($subscription->hasStripeSubscription()) {
                $this->updateStripeSubscription($subscription, $upgradePlan);
            }

            Logger::info("Subscription upgraded to Insider", [
                'subscription_id' => $subscriptionId,
                'from_plan' => $subscription->plan_id,
                'to_plan' => $upgradePlanId,
                'price_difference' => $priceDifference
            ]);

            return [
                'success' => true,
                'subscription' => $this->subscriptionRepository->find($subscriptionId),
                'price_charged' => round($priceDifference, 2),
                'payment_result' => $paymentResult,
                'message' => 'Successfully upgraded to Insider access'
            ];
        });
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
     * Get benefits of upgrading
     */
    private function getUpgradeBenefits(Subscription $subscription, SubscriptionPlan $upgradePlan): array
    {
        $benefits = [];

        if ($upgradePlan->includes_insider && !$subscription->includes_digital_access) {
            $benefits[] = [
                'icon' => '🔓',
                'title' => 'Unlock Insider Content',
                'description' => 'Immediate access to all premium Insider articles and features'
            ];
        }

        if (!$subscription->isDigital() && in_array($upgradePlan->delivery_type, ['digital', 'both'])) {
            $benefits[] = [
                'icon' => '💻',
                'title' => 'Digital Access',
                'description' => 'Read on any device with our digital platform'
            ];
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
}
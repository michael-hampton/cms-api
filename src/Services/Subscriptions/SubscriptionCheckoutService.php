<?php

namespace App\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Model;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Billing\PaymentMethodRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\PaymentProviders\PayPalPaymentProcessor;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Subscriptions\Calculators\SubscriptionPricingResolver;
use App\Services\Vouchers\VoucherService;
use Exception;

class SubscriptionCheckoutService
{
    public function __construct(
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly SubscriptionRepository     $subscriptionRepository,
        private readonly PaymentMethodRepository    $paymentMethodRepository,
        private readonly StripePaymentProcessor     $stripeProcessor,
        private readonly PayPalPaymentProcessor     $paypalProcessor,
        private readonly VoucherService $voucherService,
        private readonly SubscriptionEligibilityService $eligibilityService,
        private readonly SubscriptionPricingResolver $pricingResolver,
        private readonly Database                       $database,

    )
    {
    }

    public function getSubscriptionPlan(int $planId): ?SubscriptionPlan
    {
        return $this->planRepository->find($planId);
    }

    public function hasActiveSubscription(int $memberId, int $planId): bool
    {
        $subscription = $this->subscriptionRepository->getActiveSubscriptionForMember($memberId);
        return $subscription && $subscription->plan_id === $planId;
    }

    public function processSubscriptionCheckout(int $memberId, array $data, int $siteId): array
    {
        try {

            $paymentProvider = $this->paymentMethodRepository->findByCode($data['payment_method']);

            // Step 1: Create subscription record (PENDING)
            $subscription = $this->database->transaction(function () use ($memberId, $data, $siteId, $paymentProvider) {

                // Validate plan
                $plan = $this->planRepository->find($data['subscription_plan_id']);

                if (!$plan || !$plan->is_active) {
                    throw new Exception('Invalid subscription plan');
                }

                // Validate payment method
                $paymentMethod = $paymentProvider;
                if (!$paymentMethod || !$paymentMethod->is_active) {
                    throw new Exception('Invalid payment method');
                }

                // Check eligibility using injected service
                $eligibility = $this->eligibilityService->canMemberSubscribe($memberId, $plan->id, $siteId, true);

                if (!$eligibility['can_subscribe']) {
                    throw new Exception($eligibility['reason']);
                }

                $pricingData = [
                    'variant' => $data['variant'] ?? 'digital',
                    'pricing_tier_id' => $data['pricing_tier_id'] ?? null,
                    'voucher_code' => $data['voucher_code'] ?? null
                ];

                die('mike1');

                $resolvedPrice = $this->pricingResolver->resolve($plan, $pricingData, $memberId);

                // Defaults
                $voucherId = null;
                $discountAmount = 0;
                $finalPrice = $resolvedPrice->finalPrice;

                // Handle voucher
                if (!empty($data['voucher_code'])) {
                    $voucherValidation = $this->voucherService->validateVoucherForSubscription(
                        $data['voucher_code'],
                        $plan->id,
                        $memberId
                    );

                    if (!$voucherValidation->valid) {
                        throw new Exception($voucherValidation->message);
                    }

                    $voucherId = $voucherValidation->voucherId;
                    $discountAmount = $voucherValidation->discount;
                    $finalPrice = $voucherValidation->finalPrice;
                }

                // Create subscription (status: pending)
                return $this->createSubscription(
                    $memberId,
                    $plan,
                    $siteId,
                    $voucherId,
                    $discountAmount,
                    $finalPrice
                );
            });

            // Step 2: Process payment OUTSIDE transaction
            $plan = $subscription->plan;
            $paymentMethod = $paymentProvider;

            $voucher = null;
            if ($subscription->voucher_id !== null) {
                $voucher = $this->voucherService->getVoucherById($subscription->voucher_id);
            }

            $paymentResult = $this->processPayment(
                $subscription,
                $plan,
                $data,
                $paymentMethod,
                $voucher
            );

            if (!$paymentResult['success']) {
                // Mark subscription as failed
                $this->database->transaction(function () use ($subscription) {
                    $this->subscriptionRepository->update($subscription->id, [
                        'status' => 'failed'
                    ]);
                });

                throw new Exception($paymentResult['message'] ?? 'Payment failed');
            }

            // Step 3: Activate subscription & apply voucher
            $this->database->transaction(function () use ($subscription, $plan, $paymentResult, $memberId) {

                $this->subscriptionRepository->update($subscription->id, [
                    'payment_intent_id' => $paymentResult['payment_intent_id'] ?? null,
                    'payment_subscription_id' => $paymentResult['subscription_id'] ?? null,
                    'status' => 'active'
                ]);

                // Grant premium access
                $this->grantPlanPremiumAccess($subscription, $plan);

                // Apply voucher usage AFTER successful payment
                if ($subscription->voucher_id !== null) {
                    $this->voucherService->applyVoucher(
                        $subscription->voucher_id,
                        $memberId,
                        $subscription->discount_amount
                    );
                }
            });

            return [
                'success' => true,
                'message' => 'Subscription created successfully',
                'subscription_id' => $subscription->id,
                'redirect_url' => $paymentResult['redirect_url'] ?? null
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function createSubscription(
        int              $memberId,
        SubscriptionPlan $plan,
        int              $siteId,
        ?int             $voucherId = null,
        float            $discountAmount = 0,
        ?float           $finalPrice = null
    ): Model
    {
        // Use domain date (subscription start) as basis for all calculations
        $startDate = new \DateTime();
        $startDate->setTime(0, 0, 0); // Normalize to midnight

        $endDate = $this->calculateEndDate($startDate, $plan->billing_period);

        $subscription = $this->subscriptionRepository->create([
            'member_id' => $memberId,
            'site_id' => $siteId,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'status' => 'pending',
            'start_date' => $startDate->format('Y-m-d H:i:s'),
            'end_date' => $endDate?->format('Y-m-d H:i:s'),
            'next_billing_date' => $endDate?->format('Y-m-d H:i:s'),
            'price' => $finalPrice ?? $plan->price,
            'original_price' => $plan->price,
            'discount_amount' => $discountAmount,
            'voucher_id' => $voucherId,
            'currency' => $plan->currency,
            'auto_renew' => $plan->billing_period !== 'lifetime'
        ]);

        return $subscription;
    }

    /**
     * Grant all premium access defined in the plan
     */
    private function grantPlanPremiumAccess(Subscription $subscription, SubscriptionPlan $plan): void
    {
        $premiumGrants = $plan->getPremiumAccessGrants();

        foreach ($premiumGrants as $grant) {
            $subscription->grantPremiumAccess(
                $grant['type'],
                $grant['identifier'],
                $grant['expires_at'] ?? null
            );

            Logger::info('Premium access granted on subscription creation', [
                'subscription_id' => $subscription->id,
                'premium_type' => $grant['type'],
                'premium_identifier' => $grant['identifier']
            ]);
        }

        // Backward compatibility - set includes_digital_access if insider granted
        if ($plan->grantsPremiumAccess('newsletter', 'insider')) {
            $subscription->update(['includes_digital_access' => true]);
        }
    }

    private function calculateEndDate(\DateTime $startDate, string $billingPeriod): ?\DateTime
    {
        if ($billingPeriod === 'lifetime') {
            return null;
        }

        $endDate = clone $startDate;

        return match ($billingPeriod) {
            'monthly' => $endDate->modify('+1 month'),
            'quarterly' => $endDate->modify('+3 months'),
            'yearly' => $endDate->modify('+1 year'),
            default => $endDate->modify('+1 month')
        };
    }

    private function processPayment(
        Subscription     $subscription,
        SubscriptionPlan $plan,
        array            $data,
        $paymentMethod,
        $voucher = null
    ): array
    {
        $processor = match ($data['payment_method']) {
            'stripe' => $this->stripeProcessor,
            'paypal' => $this->paypalProcessor,
            default => throw new Exception('Unsupported payment method')
        };

        // Use the new method that handles vouchers
        if ($voucher) {
            return $processor->processSubscriptionPaymentWithVoucher(
                $subscription,
                $plan,
                $voucher,
                $data
            );
        }

        return $processor->processSubscriptionPayment(
            $subscription,
            $plan,
            $data
        );
    }

    public function getSubscriptionPlanBySlug(string $slug): ?SubscriptionPlan
    {
        return $this->planRepository->findBySlug($slug);
    }
}
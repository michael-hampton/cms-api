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
        private readonly Database                   $database
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
            return $this->database->transaction(function () use ($memberId, $data, $siteId) {
                // Validate plan
                $plan = $this->planRepository->find($data['subscription_plan_id']);
                if (!$plan || !$plan->is_active) {
                    throw new Exception('Invalid subscription plan');
                }

                // Validate payment method
                $paymentMethod = $this->paymentMethodRepository->findByCode($data['payment_method']);
                if (!$paymentMethod || !$paymentMethod->is_active) {
                    throw new Exception('Invalid payment method');
                }

                // Handle voucher if provided
                $voucherId = null;
                $discountAmount = 0;
                $finalPrice = $plan->price;

                if (!empty($data['voucher_code'])) {
                    $voucherValidation = $this->voucherService->validateVoucherForSubscription(
                        $data['voucher_code'],
                        $plan->id,
                        $memberId
                    );

                    if (!$voucherValidation['valid']) {
                        throw new Exception($voucherValidation['message']);
                    }

                    $voucher = $voucherValidation['voucher'];
                    $voucherId = $voucherValidation['voucher_id'];
                    $discountAmount = $voucherValidation['discount'];
                    $finalPrice = $voucherValidation['final_price'] ?? null;
                }

                // Create subscription with voucher data
                $subscription = $this->createSubscription(
                    $memberId,
                    $plan,
                    $siteId,
                    $voucherId,
                    $discountAmount,
                    $finalPrice
                );

                // Process payment with voucher
                $paymentResult = $this->processPayment(
                    $subscription,
                    $plan,
                    $data,
                    $paymentMethod,
                    $voucher ?? null
                );

                if (!$paymentResult['success']) {
                    throw new Exception($paymentResult['message'] ?? 'Payment failed');
                }

                // Update subscription with payment details
                $this->subscriptionRepository->update($subscription->id, [
                    'payment_intent_id' => $paymentResult['payment_intent_id'] ?? null,
                    'payment_subscription_id' => $paymentResult['subscription_id'] ?? null
                ]);

                return [
                    'success' => true,
                    'message' => 'Subscription created successfully',
                    'subscription_id' => $subscription->id,
                    'redirect_url' => $paymentResult['redirect_url'] ?? null
                ];
            });
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
        $startDate = new \DateTime();
        $endDate = $this->calculateEndDate($startDate, $plan->billing_period);

        $subscription = $this->subscriptionRepository->create([
            'member_id' => $memberId,
            'site_id' => $siteId,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'status' => 'pending',
            'start_date' => $startDate->format('Y-m-d H:i:s'),
            'end_date' => $endDate?->format('Y-m-d H:i:s'),
            'price' => $finalPrice ?? $plan->price,
            'original_price' => $plan->price,
            'discount_amount' => $discountAmount,
            'voucher_id' => $voucherId,
            'currency' => $plan->currency,
            'auto_renew' => $plan->billing_period !== 'lifetime'
        ]);

        // **GRANT PREMIUM ACCESS FROM PLAN**
        $this->grantPlanPremiumAccess($subscription, $plan);

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
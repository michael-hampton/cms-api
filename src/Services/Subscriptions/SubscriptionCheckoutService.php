<?php

namespace App\Services\Subscriptions;

use App\Events\Subscriptions\SubscriptionCreated;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Model;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\PaymentProviders\PayPalPaymentProcessor;
use App\Services\Vouchers\VoucherService;
use DateTime;
use Exception;

class SubscriptionCheckoutService
{
    public function __construct(
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly SubscriptionRepository     $subscriptionRepository,
        private readonly SubscriptionCheckoutPreparationService $preparationService,
        private readonly SubscriptionPaymentService $subscriptionPaymentService,
        private readonly PayPalPaymentProcessor     $paypalProcessor,
        private readonly VoucherService $voucherService,
        private readonly Database                       $database,
        private readonly Logger $logger,

    )
    {
    }

    /**
     * Intentionally separate from OneTimeSubscriptionCheckoutService.
     *
     * The one-time checkout stack is cart/session driven and ultimately creates
     * one-time subscriptions (auto_renew=false) via OneTimeSubscriptionService.
     * This recurring checkout flow must preserve recurring subscription
     * semantics, so we only reuse lower-level pricing/eligibility/payment
     * collaborators that are safe for recurring billing.
     */
    public function processSubscriptionCheckout(int $memberId, array $data, int $siteId): array
    {
        try {
            $prepared = $this->preparationService->prepare($memberId, $data, $siteId);

            // Step 1: Create subscription record (PENDING)
            $subscription = $this->database->transaction(function () use ($memberId, $siteId, $prepared) {
                // Create subscription (status: pending)
                return $this->createSubscription(
                    $memberId,
                    $prepared->plan,
                    $siteId,
                    $prepared->resolvedPrice->voucherId,
                    $prepared->resolvedPrice->discountAmount,
                    $prepared->resolvedPrice->finalPrice
                );
            });

            // Step 2: Process payment OUTSIDE transaction
            $paymentResult = $this->processPayment(
                $subscription,
                $prepared->plan,
                $data,
                $prepared->paymentMethod
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
            $this->database->transaction(function () use ($subscription, $prepared, $paymentResult, $memberId) {

                $this->subscriptionRepository->update($subscription->id, [
                    'payment_intent_id' => $paymentResult['payment_intent_id'] ?? null,
                    'payment_subscription_id' => $paymentResult['subscription_id'] ?? null,
                    'status' => 'active'
                ]);

                // Grant premium access
                $this->grantPlanPremiumAccess($subscription, $prepared->plan);

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
            $this->logger->error('Subscription checkout failed', [
                'member_id' => $memberId,
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);

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
        $startDate = new DateTime();
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
            'price_paid_cents' => (int)round((($finalPrice ?? $plan->price) * 100)),
            'original_price' => $plan->price,
            'discount_amount' => $discountAmount,
            'voucher_id' => $voucherId,
            'currency' => $plan->currency,
            'auto_renew' => $plan->billing_period !== 'lifetime'
        ]);

        event(new SubscriptionCreated(
            subscriptionId: (int)$subscription->id,
            planId: (int)$plan->id,
            billingPeriod: (string)$plan->billing_period,
            priceCents: (int)round(((float)($finalPrice ?? $plan->price)) * 100),
            currency: (string)($plan->currency ?? 'GBP'),
        ));

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

            $this->logger->info('Premium access granted on subscription creation', [
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

    private function calculateEndDate(DateTime $startDate, string $billingPeriod): ?DateTime
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
        $paymentMethod
    ): array
    {
        $processor = match ($data['payment_method']) {
            'stripe' => $this->subscriptionPaymentService,
            'paypal' => $this->paypalProcessor,
            default => throw new Exception('Unsupported payment method')
        };

        if ($data['payment_method'] === 'stripe') {
            return $processor->processStripeSubscriptionPayment(
                $subscription,
                $plan,
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

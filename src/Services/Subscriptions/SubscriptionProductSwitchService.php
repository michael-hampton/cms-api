<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionEndReason;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Events\Subscriptions\SubscriptionProductChanged;
use App\Framework\Authorization\MemberAuthWrapper;
use App\Framework\Database\Database;
use App\Framework\Session\Session;
use App\Framework\Support\Logger;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Shopping\CartService;
use App\Services\Shopping\OneTimeSubscriptionCheckoutService;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

class SubscriptionProductSwitchService
{
    public function __construct(
        private readonly SubscriptionRepository             $subscriptionRepository,
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly MemberRepository                   $memberRepository,
        private readonly CartService                        $cartService,
        private readonly OneTimeSubscriptionCheckoutService $checkoutService,
        private readonly SubscriptionPaymentService $subscriptionPaymentService,
        private readonly MemberAuthWrapper                  $memberAuth,
        private readonly Database                           $database,
    )
    {
    }

    /**
     * @return array{
     *     old_subscription: object,
     *     new_subscription: object
     * }
     */
    public function switch(
        int   $subscriptionId,
        int   $newPlanId,
        string $switchMode,
        string $paymentMethodId,
        float $amountToCharge,
        float $carriedOverCredit,
        int   $agentId,
        int   $siteId,
    ): array
    {
        if (!in_array($switchMode, ['transfer', 'fresh'], true)) {
            throw new InvalidArgumentException(
                "switchMode must be 'transfer' or 'fresh'."
            );
        }

        $oldSubscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$oldSubscription) {
            throw new InvalidArgumentException(
                "Subscription #{$subscriptionId} not found."
            );
        }

        if ((int)$oldSubscription->site_id !== $siteId) {
            throw new InvalidArgumentException(
                'Subscription does not belong to this site.'
            );
        }

        if ($oldSubscription->status !== SubscriptionStatus::ACTIVE->value) {
            throw new InvalidArgumentException(
                "Only active subscriptions can be switched. Current status: {$oldSubscription->status}."
            );
        }

        $newPlan = $this->planRepository->find($newPlanId);

        if (!$newPlan || !$newPlan->is_active) {
            throw new InvalidArgumentException(
                "Target plan #{$newPlanId} not found or inactive."
            );
        }

        if ((int)$newPlan->site_id !== $siteId) {
            throw new InvalidArgumentException(
                'Target plan does not belong to this site.'
            );
        }

        if ((int)$oldSubscription->plan_id === (int)$newPlanId) {
            throw new InvalidArgumentException(
                'Target plan is the same as the current plan.'
            );
        }

        $member = $this->memberRepository->find(
            (int)$oldSubscription->member_id
        );

        if (!$member) {
            throw new InvalidArgumentException(
                'Subscription member no longer exists.'
            );
        }

        if (
            $this->subscriptionRepository->hasActiveSubscriptionToPlan(
                (int)$member->id,
                (int)$newPlan->id
            )
        ) {
            throw new InvalidArgumentException(
                'Member already has an active subscription on this plan.'
            );
        }

        $this->memberAuth->login($member);

        Session::put('member_id', $member->id);

        try {
            /**
             * Inject subscription into checkout pipeline.
             */
            $this->cartService->addSubscriptionToCart(
                (int)$newPlan->id
            );

            /**
             * Process standard checkout flow.
             *
             * This creates:
             * - order
             * - local subscription
             * - all standard checkout side effects
             */
            $checkoutResult = $this->checkoutService->processCheckout(
                [
                    'payment_method_id' => $paymentMethodId,
                    'one_time_subscription' => false,
                    'admin_created' => true,

                    'subscription_switch' => true,
                    'switch_mode' => $switchMode,
                    'switching_subscription_id' => $oldSubscription->id,
                    'carried_over_credit' => $carriedOverCredit,

                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'email' => $member->email,
                    'phone' => $member->phone ?? '',

                    'saved_address' => $oldSubscription->delivery_address_id,
                ],
                $siteId
            );

            $newSubscription = $this->resolveSubscription(
                $checkoutResult
            );

            if (!$newSubscription) {
                throw new RuntimeException(
                    'Checkout completed but no subscription was created.'
                );
            }

            /**
             * Create Stripe recurring subscription.
             *
             * IMPORTANT:
             * Checkout currently creates local subscriptions/orders,
             * but recurring Stripe subscription creation still happens
             * separately via StripePaymentProcessor.
             */
            $paymentResult = $this->subscriptionPaymentService
                ->processStripeSubscriptionPayment(
                    $newSubscription,
                    $newPlan,
                    [
                        'payment_method_id' => $paymentMethodId,
                        'amount' => $amountToCharge,
                        'order_id' => $checkoutResult['order_id'] ?? null,

                        'metadata' => [
                            'type' => 'subscription_product_switch',
                            'old_subscription_id' => $oldSubscription->id,
                            'switch_mode' => $switchMode,
                            'credit_applied' => $carriedOverCredit,
                            'agent_id' => $agentId,
                        ],
                    ],
                );

            if (!($paymentResult['success'] ?? false) || !empty($paymentResult['requires_action'])) {
                throw new RuntimeException(
                    'Payment failed: ' .
                    ($paymentResult['message'] ?? 'Unknown error')
                );
            }

            $this->subscriptionRepository->update(
                $newSubscription->id,
                [
                    'payment_subscription_id' => $paymentResult['subscription_id'] ?? null,
                    'stripe_subscription_item_id' => $paymentResult['stripe_subscription_item_id'] ?? null,
                    'renewed_from_subscription_id' => $oldSubscription->id,
                    'replacement_reason' => SubscriptionEndReason::PRODUCT_CHANGE->value,
                    'carried_over_credit' => $carriedOverCredit,
                ]
            );

            return $this->database->transaction(
                function () use (
                    $oldSubscription,
                    $newSubscription,
                    $switchMode,
                    $carriedOverCredit,
                    $agentId
                ): array {
                    $now = now_datetime();

                    $this->subscriptionRepository->update(
                        $oldSubscription->id,
                        [
                            'status' => SubscriptionStatus::REPLACED->value,
                            'ended_at' => $now->format('Y-m-d H:i:s'),
                            'end_reason' => SubscriptionEndReason::PRODUCT_CHANGE->value,
                            'auto_renew' => false,
                            'replaced_by_subscription_id' => $newSubscription->id,
                        ]
                    );

                    event(new SubscriptionProductChanged(
                        memberId: (int)$oldSubscription->member_id,
                        oldSubscriptionId: $oldSubscription->id,
                        newSubscriptionId: $newSubscription->id,
                        oldPlanId: (int)$oldSubscription->plan_id,
                        newPlanId: (int)$newSubscription->plan_id,
                        switchMode: $switchMode,
                        carriedOverCredit: $carriedOverCredit,
                        agentId: $agentId,
                        timestamp: $now->format('Y-m-d H:i:s'),
                    ));

                    Logger::info('Subscription product switched', [
                        'old_subscription_id' => $oldSubscription->id,
                        'new_subscription_id' => $newSubscription->id,
                        'switch_mode' => $switchMode,
                        'agent_id' => $agentId,
                    ]);

                    return [
                        'old_subscription' => $this->subscriptionRepository->find(
                            $oldSubscription->id
                        ),

                        'new_subscription' => $this->subscriptionRepository->find(
                            $newSubscription->id
                        ),
                    ];
                }
            );
        } finally {
            $this->cartService->clear();
        }
    }

    private function resolveSubscription(
        array $checkoutResult
    ): ?object
    {
        $ids = $checkoutResult['subscription_ids']
            ?? (
            isset($checkoutResult['subscription_id'])
                ? [$checkoutResult['subscription_id']]
                : []
            );

        if (empty($ids)) {
            return null;
        }

        return $this->subscriptionRepository->find(
            (int)$ids[0]
        );
    }

    /**
     * Formula:
     * (price / total_days) × remaining_days
     */
    public function calculateCarriedOverCredit(
        object $subscription
    ): float
    {
        $price = (float)($subscription->price ?? 0);

        $startDate = $subscription->start_date;
        $endDate = $subscription->end_date;

        if ($price <= 0 || !$startDate || !$endDate) {
            return 0.00;
        }

        $start = $startDate;
        $end = $endDate;

        $now = new DateTimeImmutable();

        if ($now >= $end) {
            return 0.00;
        }

        $totalDays = max(
            1,
            (int)$start->diff($end)->days
        );

        $remainingDays = max(
            0,
            (int)$now->diff($end)->days
        );

        $dailyRate = $price / $totalDays;

        return round(
            $dailyRate * $remainingDays,
            2
        );
    }
}

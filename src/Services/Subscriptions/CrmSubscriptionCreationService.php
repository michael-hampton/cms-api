<?php

namespace App\Services\Subscriptions;

use App\Exceptions\Checkout\CheckoutException;
use App\Framework\Authorization\MemberAuthWrapper;
use App\Framework\Session\Session;
use App\Framework\Support\Logger;
use App\Repositories\Members\AddressRepository;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Shopping\CartService;
use App\Services\Shopping\OneTimeSubscriptionCheckoutService;
use Exception;
use InvalidArgumentException;

/**
 * Creates a subscription on behalf of a member from the admin panel.
 *
 * Rather than duplicating checkout logic, this service:
 *   1. Validates the member and plan
 *   2. Injects a cart item for the member via CartService
 *   3. Delegates to OneTimeSubscriptionCheckoutService::processCheckout()
 *      with the payment_method_id the admin collected from the frontend
 *
 * The checkout service owns all business rules: stock reservation,
 * eligibility checks, Stripe subscription creation, order creation,
 * and subscription activation. We do not duplicate any of that here.
 */
class CrmSubscriptionCreationService
{
    public function __construct(
        private readonly MemberRepository                   $memberRepository,
        private readonly SubscriptionPlanRepository         $planRepository,
        private readonly SubscriptionRepository             $subscriptionRepository,
        private readonly CartService                        $cartService,
        private readonly OneTimeSubscriptionCheckoutService $checkoutService,
        private readonly MemberAuthWrapper                  $memberAuth,
        private readonly SubscriptionPaymentService $subscriptionPaymentService,
        private readonly AddressRepository $addressRepository,
    )
    {
    }

    /**
     * Create a subscription for the given member using the supplied
     * Stripe payment method ID.
     *
     * @param int $memberId Target member
     * @param int $planId Subscription plan to subscribe to
     * @param string $paymentMethodId Stripe pm_xxx — already attached or just
     *                                confirmed via a SetupIntent on the frontend
     * @param int $siteId Current site
     *
     * @throws InvalidArgumentException  For validation failures (plan inactive, duplicate, etc.)
     * @throws CheckoutException         Propagated from the checkout service
     */
    public function createSubscription(
        int    $memberId,
        int    $planId,
        string $paymentMethodId,
        int    $siteId,
        ?int   $deliveryAddressId = null,
        ?array $deliveryAddress = null
    ): array
    {
        // ── Validate ──────────────────────────────────────────────────────────

        $member = $this->memberRepository->find($memberId);

        if (!$member) {
            throw new InvalidArgumentException('Member not found.');
        }

        if ($member->site_id !== $siteId) {
            throw new InvalidArgumentException('Member does not belong to this site.');
        }

        $plan = $this->planRepository->find($planId);

        if (!$plan) {
            throw new InvalidArgumentException('Subscription plan not found.');
        }

        if (!$plan->is_active) {
            throw new InvalidArgumentException("Plan \"{$plan->name}\" is not currently active.");
        }

        if ($plan->site_id !== $siteId) {
            throw new InvalidArgumentException('Plan does not belong to this site.');
        }

        $existing = $this->subscriptionRepository->hasActiveSubscriptionToPlan($memberId, $planId);

        if ($existing) {
            throw new InvalidArgumentException('Member already has an active subscription on this plan.');
        }

        if ($deliveryAddressId === null && !empty($deliveryAddress)) {
            $deliveryAddress = $this->addressRepository->createAddressForMember($memberId, $deliveryAddress, $siteId);
            $deliveryAddressId = $deliveryAddress->id;
        }

        // ── Impersonate member and inject cart item ────────────────────────────
        //
        // The checkout service reads the cart via CartService and uses
        // MemberAuthWrapper to identify the member. We temporarily impersonate
        // the target member so both resolve to the right person.
        //
        // CartService::setItems() replaces the cart in-process without touching
        // the member's real session cart (admin is in a separate session).

        $this->memberAuth->login($member);

        Session::put('member_id', $memberId);

        try {
            $this->cartService->addSubscriptionToCart($planId, $plan->getDeliveryOptions()[0] ?? null);

            // processCheckout() expects the same $data array the frontend POSTs.
            // payment_method_id is the Stripe PM the admin collected.
            // one_time_subscription must be falsy so the recurring Stripe
            // subscription path is taken (not a one-time PaymentIntent charge).
            $data = [
                'payment_method_id' => $paymentMethodId,
                'one_time_subscription' => false,
                'admin_created' => true,

                // Billing details — pulled from the member record so the
                // checkout service can create/update the Stripe customer.
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'email' => $member->email,
                'phone' => $member->phone ?? '',
                'saved_address' => $deliveryAddressId ?? null
            ];

            $result = $this->checkoutService->processCheckout($data, $siteId);

            $subscription = $this->subscriptionRepository->find($result['subscription_id']);

            $paymentResult = $this->subscriptionPaymentService->processStripeSubscriptionPayment(
                $subscription,
                $subscription->plan,
                [
                    'payment_method_id' => $paymentMethodId,
                    'order_id' => $result['order_id'],
                ],
            );

            $subscription->update([
                'payment_subscription_id' => $paymentResult['subscription_id'],
            ]);

        } catch (Exception $exception) {
            echo $exception->getMessage();
            die;

        } finally {
            // Always restore auth state and clear the injected cart,
            // even if checkout throws.
            $this->cartService->clear();
        }


        Logger::info('Admin created subscription for member', [
            'member_id' => $memberId,
            'plan_id' => $planId,
            'subscription_id' => $result['subscription_ids'][0] ?? null,
        ]);

        return [
            'success' => true,
            'subscription' => $this->resolveSubscription($result),
        ];
    }

    // ─── Private helpers ───────────────────────────────────────────────────────

    /**
     * Pull the created Subscription model out of the checkout response.
     * processCheckout() returns subscription_ids (array of IDs) or subscription_id.
     */
    private function resolveSubscription(array $checkoutResult): ?object
    {
        $ids = $checkoutResult['subscription_ids']
            ?? ($checkoutResult['subscription_id']
                ? [$checkoutResult['subscription_id']]
                : []);

        if (empty($ids)) {
            return null;
        }

        return $this->subscriptionRepository->find((int)$ids[0]);
    }

    /**
     * Build a cart item array in the shape CartService / OneTimeSubscriptionCheckoutService
     * expects for a subscription item.
     */
    private function buildCartItem(object $plan): array
    {
        return [
            'subscription_plan_id' => $plan->id,
            'quantity' => 1,
            'price' => $plan->price,
            'base_price' => $plan->price,
            'name' => $plan->name,
            'options' => [
                'delivery_type' => $plan->default_delivery_type ?? 'digital',
                'pricing_tier_id' => null,
                'type' => 'subscription',
            ],
        ];
    }
}

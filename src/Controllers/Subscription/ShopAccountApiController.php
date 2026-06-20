<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Enums\Orders\OrderCancellationReason;
use App\Enums\Subscriptions\SubscriptionCancellationReason;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Members\AddressRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Order\OrderUpdateService;
use App\Services\Billing\Stripe\StripeCustomerPaymentMethodService;
use App\Services\Subscriptions\SubscriptionCancellationService;
use App\Services\Subscriptions\SubscriptionCancellationFlowProvider;
use App\Services\Subscriptions\SubscriptionPauseService;
use App\Services\Subscriptions\SubscriptionListingService;
use App\Services\Subscriptions\SubscriptionPaymentRecoveryService;

/**
 * ShopAccountApiController
 *
 * Handles all JSON mutation endpoints called by the account UI.
 * Ownership is validated in this controller before service calls.
 * Business logic lives entirely in the service layer.
 *
 * Routes:
 *   POST /account/subscriptions/{id}/cancel    → cancelSubscription()
 *   POST /account/subscriptions/{id}/pause     → pauseSubscription()
 *   POST /account/subscriptions/{id}/resume    → resumeSubscription()
 *   POST /account/orders/{id}/cancel           → cancelOrder()
 *   GET  /account/billing/payment-methods      → paymentMethods()
 *   POST /account/billing/set-default          → setDefaultCard()
 *   POST /account/billing/remove-card          → removeCard()
 */
class ShopAccountApiController extends Controller
{
    private SubscriptionCancellationFlowProvider $cancellationFlowProvider;

    public function __construct(
        private readonly SubscriptionCancellationService $subscriptionCancellationService,
        private readonly SubscriptionPauseService        $subscriptionPauseService,
        private readonly OrderUpdateService              $orderUpdateService,
        private readonly SubscriptionRepository          $subscriptionRepository,
        private readonly OrderRepository                 $orderRepository,
        private readonly StripeCustomerPaymentMethodService $paymentMethodService,
        private readonly AddressRepository                 $addressRepository,
        private readonly SubscriptionListingService        $subscriptionListingService,
        private readonly SubscriptionPaymentRecoveryService $paymentRecoveryService,
        ?SubscriptionCancellationFlowProvider $cancellationFlowProvider = null,
    )
    {
        parent::__construct();
        $this->cancellationFlowProvider = $cancellationFlowProvider
            ?? new SubscriptionCancellationFlowProvider();
    }

    // ── Subscription actions ──────────────────────────────────────────────────

    public function cancelSubscription(int $id, Request $request): mixed
    {
        $member = MemberAuth::getMember();
        $reason = $request->input('reason', '');
        $other = $request->input('other_text');

        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        if (!$this->isValidSubscriptionReason($reason)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Please select a cancellation reason.',
            ], 422);
        }

        if (!$this->subscriptionOwnedByMember($id, $member->id)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        $subscription = $this->subscriptionRepository->find($id);
        if (!$subscription
            || !$this->cancellationFlowProvider->canCancel($subscription)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'This subscription cannot be cancelled.',
            ], 422);
        }

        try {
            $result = $this->subscriptionCancellationService->cancelSubscription($id, [
                'cancel_at_period_end' => true,
                'cancellation_reason' => $reason,
                'cancellation_notes' => $reason === SubscriptionCancellationReason::Other->value
                    ? $this->sanitize($other)
                    : null,
            ]);

            if (!($result['success'] ?? false)) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Cancellation failed. Please try again.',
                ], 422);
            }

            $subscription = $this->subscriptionRepository->find($id);
            return $this->jsonResponse([
                'success' => true,
                'subscription' => $subscription
                    ? $this->subscriptionListingService->formatSubscriptionForListing($subscription)
                    : null,
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => 'Cancellation failed. Please try again.'], 422);
        } catch (\Throwable) {
            return $this->jsonResponse(['success' => false, 'message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function reactivateSubscription(int $id, Request $request): mixed
    {
        $member = MemberAuth::getMember();

        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        if (!$this->subscriptionOwnedByMember($id, $member->id)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        try {
            $result = $this->subscriptionCancellationService->reactivateSubscription($id);
            $subscription = $result['subscription'];

            return $this->jsonResponse([
                'success' => true,
                'message' => $result['message'] ?? 'Subscription reactivated.',
                'subscription' => $this->subscriptionListingService->formatSubscriptionForListing($subscription),
            ]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function settlePayment(int $id, Request $request): mixed
    {
        $member = MemberAuth::getMember();
        $subscription = $this->subscriptionRepository->find($id);

        try {
            $url = $member && $subscription
                ? $this->paymentRecoveryService->settlementUrl(
                    $subscription,
                    (int)$member->id
                )
                : throw new \RuntimeException('Subscription not found.');

            return $this->redirect($url);
        } catch (\Throwable $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function pauseSubscription(int $id, Request $request): mixed
    {
        $member = MemberAuth::getMember();
        $pauseUntil = $request->input('pause_until');

        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        // canPause() checks both ownership and status eligibility
        if (!$this->subscriptionPauseService->canPause($id, $member->id)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'This subscription cannot be paused.',
            ], 422);
        }

        try {
            $subscription = $this->subscriptionPauseService->pause($id, $member->id, $pauseUntil);

            return $this->jsonResponse([
                'success' => true,
                'status' => $subscription->status,
                'pause_until' => $subscription->pause_until,
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            echo $e->getMessage();
            die;
            return $this->jsonResponse(['success' => false, 'message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function resumeSubscription(int $id, Request $request): mixed
    {
        $member = MemberAuth::getMember();

        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        // canResume() checks both ownership and status eligibility
        if (!$this->subscriptionPauseService->canResume($id, $member->id)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'This subscription is not paused.',
            ], 422);
        }

        try {
            $subscription = $this->subscriptionPauseService->resume($id, $member->id);

            return $this->jsonResponse([
                'success' => true,
                'status' => $subscription->status,
                'next_billing_date' => $subscription->next_billing_date,
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable) {
            return $this->jsonResponse(['success' => false, 'message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    // ── Order actions ─────────────────────────────────────────────────────────

    public function cancelOrder(int $id, Request $request): mixed
    {
        $member = MemberAuth::getMember();
        $reason = $request->input('reason', '');

        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        if (!$this->isValidOrderReason($reason)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Please select a cancellation reason.',
            ], 422);
        }

        if (!$this->orderCancellableByMember($id, $member->id)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'This order cannot be cancelled. It may have already been dispatched.',
            ], 404);
        }

        try {
            $this->orderUpdateService->cancel($id, $reason, $member->id);

            return $this->jsonResponse(['success' => true]);

        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable) {
            return $this->jsonResponse(['success' => false, 'message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    // ── Billing ───────────────────────────────────────────────────────────────

    public function paymentMethods(Request $request): mixed
    {
        $member = MemberAuth::getMember();

        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        $methods = $this->paymentMethodService->getCustomerPaymentMethods($member);
        $billingAddress = !empty($member) ? $this->addressRepository->getBillingAddressesForMember($member->id) : null;

        return $this->jsonResponse([
            'success' => true,
            'payment_methods' => $methods['payment_methods'] ?? [],
            'default_method' => $methods['default_payment_method_id'] ?? null,
            'billing_address' => $billingAddress,
        ]);
    }

    public function createSetupIntent(Request $request): mixed
    {
        $member = MemberAuth::getMember();
        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        $result = $this->paymentMethodService->createSetupIntent($member);

        return $this->jsonResponse($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function finaliseSetupIntent(Request $request): mixed
    {
        $member = MemberAuth::getMember();
        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        $setupIntentId = trim((string)$request->input('setup_intent_id', ''));
        if ($setupIntentId === '') {
            return $this->jsonResponse(['success' => false, 'message' => 'SetupIntent ID required.'], 422);
        }

        $result = $this->paymentMethodService->finaliseSetupIntent(
            $member,
            $setupIntentId,
            (bool)$request->input('set_default', false)
        );

        return $this->jsonResponse($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function setDefaultCard(Request $request): mixed
    {
        $member = MemberAuth::getMember();
        $paymentMethodId = $request->input('payment_method_id');

        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        if (empty($paymentMethodId)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Payment method ID required.'], 422);
        }

        // Guard: ensure the payment method belongs to this customer before
        // promoting it — prevents a member from setting another member's card.
        $result = $this->paymentMethodService->setDefaultPaymentMethod(
            (string) $member->stripe_customer_id,
            $paymentMethodId
        );

        if (!($result['success'] ?? false)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $result['message'] ?? 'Payment method could not be removed.',
                'error_code' => $result['error_code'] ?? null,
            ], ($result['error_code'] ?? null) === 'last_required_method' ? 422 : 404);
        }

        return $this->jsonResponse(['success' => true]);
    }

    public function removeCard(Request $request): mixed
    {
        $member = MemberAuth::getMember();
        $paymentMethodId = $request->input('payment_method_id');

        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        if (empty($paymentMethodId)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Payment method ID required.'], 422);
        }

        // Guard: verify the payment method belongs to this customer before
        // detaching — prevents a member from removing another member's card.
        $result = $this->paymentMethodService->removePaymentMethod($member, $paymentMethodId);

        if (!($result['success'] ?? false)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Payment method not found.'], 404);
        }

        return $this->jsonResponse(['success' => true]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function isValidSubscriptionReason(string $value): bool
    {
        return SubscriptionCancellationReason::tryFrom($value) !== null;
    }

    private function isValidOrderReason(string $value): bool
    {
        return OrderCancellationReason::tryFrom($value) !== null;
    }

    /**
     * Ownership guard for subscriptions.
     * Returns false for both "not found" and "wrong member" — avoids IDOR leaks.
     */
    private function subscriptionOwnedByMember(int $subscriptionId, int $memberId): bool
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        return $subscription
            && (int)$subscription->member_id === $memberId;
    }

    /**
     * Ownership + eligibility guard for order cancellation.
     * Returns false for "not found", "wrong member", or "already dispatched/completed".
     */
    private function orderCancellableByMember(int $orderId, int $memberId): bool
    {
        $order = $this->orderRepository->find($orderId);

        if (!$order || (int)$order->user_id !== $memberId) {
            return false;
        }

        return $order->canBeCancelled();
    }

    private function sanitize(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        $trimmed = trim($text);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, 1000);
    }
}

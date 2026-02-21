<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Order\OrderUpdateService;
use App\Services\Subscriptions\SubscriptionCancellationService;
use App\Services\Subscriptions\SubscriptionPauseService;

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
 *   GET  /account/billing/payment-methods      → paymentMethods()   [Stripe stub]
 *   POST /account/billing/set-default          → setDefaultCard()   [Stripe stub]
 *   POST /account/billing/remove-card          → removeCard()       [Stripe stub]
 */
class ShopAccountApiController extends Controller
{
    public function __construct(
        private readonly SubscriptionCancellationService $subscriptionCancellationService,
        private readonly SubscriptionPauseService        $subscriptionPauseService,
        private readonly OrderUpdateService              $orderUpdateService,
        private readonly SubscriptionRepository          $subscriptionRepository,
        private readonly OrderRepository                 $orderRepository,
    )
    {
        parent::__construct();
    }

    // ── Subscription actions ──────────────────────────────────────────────────

    public function cancelSubscription(int $id, Request $request): mixed
    {
        $member = MemberAuth::getMember();
        $reason = $request->input('reason', '');
        $other = $request->input('other_text');

        if (!$this->isValidSubscriptionReason($reason)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Please select a cancellation reason.',
            ], 422);
        }

        if (!$this->subscriptionOwnedByMember($id, $member->id)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        try {
            // The existing service handles Stripe cancellation, refunds, and
            // premium access revocation. cancel_at_period_end = true means the
            // member keeps access until their current period ends.
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

            return $this->jsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable) {
            return $this->jsonResponse(['success' => false, 'message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    private function isValidSubscriptionReason(string $value): bool
    {
        return SubscriptionCancellationReason::tryFrom($value) !== null;
    }

    /**
     * Ownership guard for subscriptions.
     * Returns false for both "not found" and "wrong member" — avoids IDOR leaks.
     */
    private function subscriptionOwnedByMember(int $subscriptionId, int $memberId): bool
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);
        return $subscription && (int)$subscription->member_id === $memberId;
    }

    // ── Order actions ─────────────────────────────────────────────────────────

    private function sanitize(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        $trimmed = trim($text);
        return $trimmed === '' ? null : mb_substr($trimmed, 0, 1000);
    }

    // ── Billing stubs ─────────────────────────────────────────────────────────

    public function pauseSubscription(int $id, Request $request): mixed
    {
        $member = $this->memberAuth->getMember();
        $pauseUntil = $request->input('pause_until');

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
        } catch (\Throwable) {
            return $this->jsonResponse(['success' => false, 'message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function resumeSubscription(int $id, Request $request): mixed
    {
        $member = $this->memberAuth->getMember();

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

    public function cancelOrder(int $id, Request $request): mixed
    {
        $member = $this->memberAuth->getMember();
        $reason = $request->input('reason', '');

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
            // OrderUpdateService::cancel() handles status transitions, history
            // logging, and emits OrderCancelledEvent. The reason is appended to
            // admin_notes as the service doesn't have a structured reason field.
            // TODO: Add cancellation_reason column + structured storage to OrderUpdateService.
            $this->orderUpdateService->cancel($id, $reason, $member->id);

            return $this->jsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable) {
            return $this->jsonResponse(['success' => false, 'message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function isValidOrderReason(string $value): bool
    {
        return OrderCancellationReason::tryFrom($value) !== null;
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

    public function paymentMethods(Request $request): mixed
    {
        $this->memberAuth->getMember();
        // TODO: Inject StripePaymentProcessor, call listPaymentMethods($member->stripe_customer_id)
        return $this->jsonResponse([
            'success' => true,
            'payment_methods' => [],
            'billing_address' => null,
        ]);
    }

    public function setDefaultCard(Request $request): mixed
    {
        $this->memberAuth->getMember();
        $paymentMethodId = $request->input('payment_method_id');

        if (empty($paymentMethodId)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Payment method ID required.'], 422);
        }

        // TODO: $this->stripeProcessor->setDefaultPaymentMethod($member->stripe_customer_id, $paymentMethodId)
        return $this->jsonResponse(['success' => true]);
    }

    public function removeCard(Request $request): mixed
    {
        $this->memberAuth->getMember();
        $paymentMethodId = $request->input('payment_method_id');

        if (empty($paymentMethodId)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Payment method ID required.'], 422);
        }

        // TODO: Verify payment method belongs to this customer before detaching.
        // $this->stripeProcessor->detachPaymentMethod($paymentMethodId)
        return $this->jsonResponse(['success' => true]);
    }
}
<?php

namespace App\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Order\OrderStateManager;
use App\Services\Billing\Payments\PaymentRecorder;
use App\Services\Billing\PaymentService;
use App\Services\Billing\StripeSubscriptionOrchestrator;
use Exception;

/**
 * Handles subscription payment lifecycle.
 *
 * processStripeSubscriptionPayment now delegates Stripe interaction to
 * StripeSubscriptionOrchestrator instead of calling StripePaymentProcessor
 * directly. This means:
 *   - Customer creation/retrieval lives in StripeCustomerGateway
 *   - Subscription creation (standard/trial/intro) lives in SubscriptionBillingService
 *   - This class remains focused on payment recording and local state transitions
 */
class SubscriptionPaymentService
{
    public function __construct(
        private readonly PaymentRepository           $paymentRepository,
        private readonly SubscriptionRepository      $subscriptionRepository,
        private readonly PaymentService              $paymentService,
        private readonly StripeSubscriptionOrchestrator $subscriptionOrchestrator,
        private readonly PaymentRecorder             $paymentRecorder,
        private readonly SubscriptionStateManager    $subscriptionStateManager,
        private readonly OrderStateManager           $orderStateManager,
        private readonly OrderRepository             $orderRepository,
        private readonly Database                    $database,
    ) {}

    /**
     * Create a Stripe subscription and record the outcome locally.
     *
     * The orchestrator handles: pricing tier resolution, customer get-or-create,
     * payment method attachment, gateway dispatch, and Stripe ID persistence.
     * This method handles: payment recording and local state transitions.
     */
    public function processStripeSubscriptionPayment(
        Subscription     $subscription,
        SubscriptionPlan $plan,
        array            $data = [],
    ): array {
        // ── 1. Create Stripe subscription via orchestrator ───────────────────
        $member = $subscription->member;

        $stripeResult = $this->subscriptionOrchestrator->create(
            $subscription,
            $plan,
            $member,
            $data,
        );

        // Map StripeSubscriptionResultDto to the shape the rest of this method expects
        $stripeResponse = [
            'success'                     => true,
            'subscription_id'             => $stripeResult->stripeSubscriptionId,
            'status'                      => $stripeResult->status,
            'customer_id'                 => $stripeResult->stripeCustomerId,
            'payment_intent_id'           => $stripeResult->paymentIntentId,
            'requires_action'             => $stripeResult->requiresAction,
            'payment_intent_client_secret'=> $stripeResult->paymentIntentClientSecret,
            'current_period_start'        => $stripeResult->currentPeriodStart,
            'current_period_end'          => $stripeResult->currentPeriodEnd,
        ];

        // ── 2. Resolve invoice amount ────────────────────────────────────────
        $invoiceAmountCents = $this->resolveInvoiceAmountCents(
            $stripeResponse,
            $subscription,
            $data,
        );

        $orderId = $data['order_id'] ?? null;

        // ── 3. Record payment ────────────────────────────────────────────────
        $payment = $this->paymentRecorder->recordSubscriptionStripePayment(
            $subscription,
            $plan,
            [
                'amount_cents'        => $invoiceAmountCents,
                'payment_intent_id' => $stripeResult->paymentIntentId ?? null,
                'status' => $this->mapStripeStatusToPaymentStatus($stripeResult->status ?? 'pending'),
                'order_id'            => $orderId,
                'transaction_id'      => $stripeResponse['payment_intent_id'] ?? $stripeResponse['subscription_id'],
                'stripe_subscription_id' => $stripeResponse['subscription_id'],
                'stripe_customer_id'  => $stripeResponse['customer_id'],
                'invoice_tax_cents'   => $stripeResponse['invoice_tax_cents'] ?? null,
            ],
        );

        // ── 4. Transition state only for immediately active subscriptions ────
        $isActive   = $stripeResponse['status'] === 'active';
        $noAction   = !$stripeResponse['requires_action'];

        if ($isActive && $noAction) {
            $this->paymentRecorder->markCompleted($payment);

            $this->subscriptionStateManager->markActiveFromStripe(
                $subscription,
                $stripeResponse['current_period_start'],
                $stripeResponse['current_period_end'],
            );

            if ($orderId) {
                $this->orderStateManager->markPaid($orderId);
            }
        }

        return [
            'success'                      => true,
            'payment_id'                   => $payment->id,
            'requires_action'              => $stripeResponse['requires_action'],
            'payment_intent_client_secret' => $stripeResponse['payment_intent_client_secret'] ?? null,
            'subscription_id'              => $stripeResponse['subscription_id'],
            'status'                       => $stripeResponse['status'],
        ];
    }

    private function mapStripeStatusToPaymentStatus(string $status): string
    {
        return match ($status) {
            'active' => 'completed',
            'trialing' => 'completed',
            'incomplete' => 'processing',
            'incomplete_expired' => 'failed',
            'past_due' => 'failed',
            'canceled' => 'cancelled',
            'unpaid' => 'pending',
            default => 'pending'
        };
    }

    // ── Existing methods unchanged below ─────────────────────────────────────

    public function createInitialSubscriptionPayment(int $subscriptionId, int $memberId): Payment
    {
        return $this->database->transaction(function () use ($subscriptionId, $memberId) {
            $subscription = $this->subscriptionRepository->find($subscriptionId);

            if (!$subscription) {
                throw new Exception('Subscription not found');
            }

            if ($subscription->member_id !== $memberId) {
                throw new Exception('Subscription does not belong to member');
            }

            return $this->paymentRepository->create([
                'subscription_id' => $subscriptionId,
                'member_id'       => $memberId,
                'site_id'         => $subscription->site_id,
                'payment_method'  => 'stripe',
                'payment_provider'=> 'stripe',
                'amount'          => $subscription->price,
                'currency'        => $subscription->currency,
                'status'          => 'pending',
                'metadata'        => ['subscription_initial_payment' => true],
            ]);
        });
    }

    public function createRecurringPayment(int $subscriptionId): Payment
    {
        return $this->database->transaction(function () use ($subscriptionId) {
            $subscription = $this->subscriptionRepository->find($subscriptionId);

            if (!$subscription->isDueForRenewal()) {
                throw new Exception('Subscription is not due for renewal');
            }

            $alreadyPending = $this->subscriptionRepository->hasPendingPaymentForCycle($subscriptionId);

            if ($alreadyPending) {
                throw new Exception('A pending payment already exists for this cycle');
            }

            return $this->paymentRepository->create([
                'subscription_id' => $subscriptionId,
                'site_id'         => $subscription->site_id,
                'payment_method'  => 'stripe',
                'payment_provider'=> 'stripe',
                'amount'          => $subscription->price,
                'currency'        => $subscription->currency,
                'status'          => 'pending',
                'metadata'        => ['subscription_renewal' => true],
            ]);
        });
    }

    public function completeSubscriptionPayment(int $paymentId): Payment
    {
        return $this->database->transaction(function () use ($paymentId) {
            $payment = $this->paymentRepository->find($paymentId);

            if (!$payment->isSubscriptionPayment()) {
                throw new Exception('Payment is not a subscription payment');
            }

            $payment = $this->paymentService->completePayment($paymentId);

            $subscription = $this->subscriptionRepository->find($payment->subscription_id);

            $this->subscriptionRepository->updateLastPaymentDate($subscription->id);
            $nextBillingDate = $this->calculateNextBillingDate(
                new \DateTime(),
                $subscription->plan->billing_period
            );

            $this->subscriptionRepository->updateNextBillingDate(
                $subscription->id,
                $nextBillingDate
            );
            $this->subscriptionRepository->update($subscription->id, ['status' => 'active']);

            return $payment;
        });
    }

    private function calculateNextBillingDate(\DateTime $from, string $period): \DateTime
    {
        return match ($period) {
            'weekly'  => (clone $from)->modify('+1 week'),
            'monthly' => (clone $from)->modify('+1 month'),
            'yearly'  => (clone $from)->modify('+1 year'),
            default   => throw new \InvalidArgumentException("Invalid billing period: {$period}"),
        };
    }

    public function handleFailedSubscriptionPayment(int $paymentId, string $errorMessage): Payment
    {
        return $this->database->transaction(function () use ($paymentId, $errorMessage) {
            $payment = $this->paymentRepository->find($paymentId);

            if (!$payment->isSubscriptionPayment()) {
                throw new Exception('Payment is not a subscription payment');
            }

            $payment = $this->paymentService->failPayment($paymentId, $errorMessage);

            $subscription = $this->subscriptionRepository->find($payment->subscription_id);
            $failedCount  = $this->paymentRepository->countSubscriptionPayments($subscription->id, 'failed');

            $this->subscriptionRepository->markAsPastDue($subscription->id);

            if ($failedCount >= 3) {
                $this->subscriptionRepository->cancelSubscription($subscription->id);
            }

            return $payment;
        });
    }

    public function getSubscriptionPaymentHistory(int $subscriptionId): array
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);
        $payments     = $this->paymentRepository->findBySubscriptionId($subscriptionId);

        return [
            'subscription' => $subscription,
            'payments'     => $payments,
            'total_paid'   => $payments->where('status', 'completed')->sum('amount'),
            'failed_count' => $payments->where('status', 'failed')->count(),
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Determine the amount in cents to record on the payment.
     *
     * Priority order:
     *   1. Stripe invoice amount (when non-zero — e.g. immediate charge)
     *   2. subscription->price_paid_cents (set at checkout time)
     *   3. null (requires_action — amount is not yet confirmed)
     *
     * For trials the invoice amount is 0; we fall back to price_paid_cents
     * so the payment record reflects the eventual charge amount.
     */
    private function resolveInvoiceAmountCents(
        array        $stripeResponse,
        Subscription $subscription,
        array        $data,
    ): ?int {
        if ($stripeResponse['requires_action']) {
            return null;
        }

        $invoiceCents = $stripeResponse['invoice_amount_cents'] ?? null;

        if ($invoiceCents !== null && $invoiceCents > 0) {
            return $invoiceCents;
        }

        // Invoice is zero (trial) or absent — use price_paid_cents from checkout
        if ($subscription->price_paid_cents !== null && $subscription->price_paid_cents > 0) {
            return $subscription->price_paid_cents;
        }

        // Fallback: derive from order total if an order_id was passed
        if (!empty($data['order_id'])) {
            $order = $this->orderRepository->find($data['order_id']);
            if ($order) {
                return (int) round($order->total * 100);
            }
        }

        return null;
    }
}
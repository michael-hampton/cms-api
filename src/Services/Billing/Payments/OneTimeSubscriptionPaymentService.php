<?php

namespace App\Services\Billing\Payments;

use App\Framework\Database\Database;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Stripe\Contracts\StripePaymentIntentGatewayInterface;
use Exception;
use Stripe\StripeClient;

class OneTimeSubscriptionPaymentService
{
    private StripeClient $stripe;

    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly OrderRepository $orderRepository,
        private readonly StripePaymentIntentGatewayInterface $paymentIntentGateway,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly Database $database,
        ?StripeClient $stripeClient = null,
    ) {
        if ($stripeClient) {
            $this->stripe = $stripeClient;
        } else {
            $secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? config('payment.stripe.secret_key');
            $this->stripe = new StripeClient($secretKey);
        }
    }

    public function confirmPayment(
        string $paymentIntentId,
        int $orderId,
        int $siteId,
        mixed $subscriptionIds = null,
    ): array {
        $paymentIntent = $this->paymentIntentGateway->retrieve($paymentIntentId);

        if (!$paymentIntent->success) {
            return [
                'success' => false,
                'message' => $paymentIntent->errorMessage ?? 'Unable to retrieve payment',
                'error_code' => $paymentIntent->errorCode,
            ];
        }

        if ($paymentIntent->status !== 'succeeded') {
            return [
                'success' => false,
                'message' => 'Payment not completed',
            ];
        }

        if (!is_array($subscriptionIds)) {
            $subscriptionIds = $subscriptionIds ? [$subscriptionIds] : [];
        }

        $this->persistPaymentMethod($paymentIntent->customerId, $paymentIntent->paymentMethodId);

        $payment = $this->database->transaction(function () use (
            $paymentIntent,
            $orderId,
            $siteId,
            $subscriptionIds,
        ) {
            $payment = $this->paymentRepository->create([
                'order_id' => $orderId,
                'subscription_id' => count($subscriptionIds) === 1 ? $subscriptionIds[0] : null,
                'site_id' => $siteId,
                'payment_method' => 'stripe',
                'payment_provider' => 'stripe',
                'transaction_id' => $paymentIntent->paymentIntentId,
                'payment_intent_id' => $paymentIntent->paymentIntentId,
                'status' => 'completed',
                'amount' => ($paymentIntent->amountCents ?? 0) / 100,
                'currency' => strtoupper($paymentIntent->currency ?? 'usd'),
                'paid_at' => date('Y-m-d H:i:s'),
                'metadata' => [
                    'subscription_ids' => $subscriptionIds,
                    'order_id' => $orderId,
                    'one_time_subscription' => true,
                    'multiple_subscriptions' => count($subscriptionIds) > 1,
                    'stripe_customer_id' => $paymentIntent->customerId,
                    'payment_method_saved' => !empty($paymentIntent->customerId),
                ],
            ]);

            $this->orderRepository->update($orderId, [
                'status' => 'completed',
                'payment_status' => 'paid',
            ]);

            if (!empty($paymentIntent->paymentMethodId)) {
                foreach ($subscriptionIds as $subscriptionId) {
                    $this->subscriptionRepository->update((int) $subscriptionId, [
                        'default_payment_method' => $paymentIntent->paymentMethodId,
                    ]);
                }
            }

            return $payment;
        });

        return [
            'success' => true,
            'payment_id' => $payment->id,
            'transaction_id' => $paymentIntent->paymentIntentId,
        ];
    }

    private function persistPaymentMethod(?string $customerId, ?string $paymentMethodId): void
    {
        if (!$customerId || !$paymentMethodId) {
            return;
        }

        try {
            $paymentMethod = $this->stripe->paymentMethods->retrieve($paymentMethodId);

            if ($paymentMethod->customer !== $customerId) {
                $this->stripe->paymentMethods->attach($paymentMethodId, [
                    'customer' => $customerId,
                ]);
            }

            $this->stripe->customers->update($customerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ]);
        } catch (Exception $e) {
            error_log('Failed to save payment method: ' . $e->getMessage());
        }
    }
}
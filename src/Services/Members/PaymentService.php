<?php

namespace App\Services\Members;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Payment;
use App\Repositories\Members\OrderRepository;
use App\Repositories\Members\PaymentMethodRepository;
use App\Repositories\Members\PaymentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use Exception;

class PaymentService
{
    private Database $database;

    public function __construct(
        private readonly PaymentRepository      $paymentRepository,
        public readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly OrderRepository        $orderRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        ?Database                               $database = null
    )
    {
        $this->database = $database ?? Database::getInstance();
    }

    public function createPayment(int $orderId, array $data, int $siteId): Payment
    {
        return $this->database->transaction(function () use ($orderId, $data, $siteId) {
            // Validate order exists
            $order = $this->orderRepository->find($orderId);
            if (!$order) {
                throw new Exception('Order not found');
            }

            // Validate payment method
            $paymentMethod = $this->paymentMethodRepository->findByCode($data['payment_method']);
            if (!$paymentMethod || !$paymentMethod->isActive()) {
                throw new Exception('Invalid or inactive payment method');
            }

            // Prepare payment data
            $paymentData = [
                'order_id' => $orderId,
                'site_id' => $siteId,
                'payment_method' => $data['payment_method'],
                'payment_provider' => $paymentMethod->provider,
                'amount' => $data['amount'] ?? $order->total,
                'currency' => $data['currency'] ?? $order->currency ?? 'GBP',
                'status' => 'pending',
                'transaction_id' => $data['transaction_id'] ?? null,
                'payment_intent_id' => $data['payment_intent_id'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ];

            $payment = $this->paymentRepository->create($paymentData);

            Logger::info("Payment created", [
                'payment_id' => $payment->id,
                'order_id' => $orderId,
                'amount' => $payment->amount
            ]);

            return $payment;
        });
    }

    public function processPayment(int $paymentId, array $processingData = []): Payment
    {
        return $this->database->transaction(function () use ($paymentId, $processingData) {
            $payment = $this->paymentRepository->find($paymentId);
            if (!$payment) {
                throw new Exception('Payment not found');
            }

            if (!$payment->isPending() && !$payment->canBeRetried()) {
                throw new Exception('Payment cannot be processed in current status');
            }

            // Update payment to processing
            $this->paymentRepository->update($paymentId, [
                'status' => 'processing',
                'transaction_id' => $processingData['transaction_id'] ?? $payment->transaction_id,
                'payment_intent_id' => $processingData['payment_intent_id'] ?? $payment->payment_intent_id,
                'metadata' => array_merge($payment->metadata ?? [], $processingData['metadata'] ?? [])
            ]);

            Logger::info("Payment processing started", [
                'payment_id' => $paymentId,
                'order_id' => $payment->order_id
            ]);

            return $this->paymentRepository->find($paymentId);
        });
    }

    public function completePayment(int $paymentId, array $completionData = []): Payment
    {
        return $this->database->transaction(function () use ($paymentId, $completionData) {
            $payment = $this->paymentRepository->find($paymentId);
            if (!$payment) {
                throw new Exception('Payment not found');
            }

            if ($payment->isCompleted()) {
                throw new Exception('Payment is already completed');
            }

            // Update payment
            $updateData = [
                'status' => 'completed',
                'paid_at' => date('Y-m-d H:i:s'),
                'transaction_id' => $completionData['transaction_id'] ?? $payment->transaction_id,
                'metadata' => array_merge($payment->metadata ?? [], $completionData['metadata'] ?? [])
            ];

            $this->paymentRepository->update($paymentId, $updateData);

            // Update order payment status
            $order = $this->orderRepository->find($payment->order_id);
            if ($order && $order->payment_status !== 'paid') {
                $this->orderRepository->update($order->id, [
                    'payment_status' => 'paid'
                ]);
            }

            Logger::info("Payment completed", [
                'payment_id' => $paymentId,
                'order_id' => $payment->order_id,
                'amount' => $payment->amount
            ]);

            return $this->paymentRepository->find($paymentId);
        });
    }

    public function failPayment(int $paymentId, string $errorMessage, array $errorData = []): Payment
    {
        return $this->database->transaction(function () use ($paymentId, $errorMessage, $errorData) {
            $payment = $this->paymentRepository->find($paymentId);
            if (!$payment) {
                throw new Exception('Payment not found');
            }

            $this->paymentRepository->update($paymentId, [
                'status' => 'failed',
                'error_message' => $errorMessage,
                'failed_at' => date('Y-m-d H:i:s'),
                'metadata' => array_merge($payment->metadata ?? [], $errorData)
            ]);

            // Update order payment status
            $order = $this->orderRepository->find($payment->order_id);
            if ($order && $order->payment_status !== 'failed') {
                $this->orderRepository->update($order->id, [
                    'payment_status' => 'failed'
                ]);
            }

            Logger::error("Payment failed", [
                'payment_id' => $paymentId,
                'order_id' => $payment->order_id,
                'error' => $errorMessage
            ]);

            return $this->paymentRepository->find($paymentId);
        });
    }

    public function cancelPayment(int $paymentId, ?string $reason = null): Payment
    {
        return $this->database->transaction(function () use ($paymentId, $reason) {
            $payment = $this->paymentRepository->find($paymentId);
            if (!$payment) {
                throw new Exception('Payment not found');
            }

            if ($payment->isCompleted()) {
                throw new Exception('Cannot cancel completed payment. Please process a refund instead.');
            }

            $metadata = $payment->metadata ?? [];
            if ($reason) {
                $metadata['cancellation_reason'] = $reason;
            }

            $this->paymentRepository->update($paymentId, [
                'status' => 'cancelled',
                'metadata' => $metadata
            ]);

            Logger::info("Payment cancelled", [
                'payment_id' => $paymentId,
                'order_id' => $payment->order_id,
                'reason' => $reason
            ]);

            return $this->paymentRepository->find($paymentId);
        });
    }

    public function refundPayment(int $paymentId, float $amount, string $reason): Payment
    {
        return $this->database->transaction(function () use ($paymentId, $amount, $reason) {
            $payment = $this->paymentRepository->find($paymentId);
            if (!$payment) {
                throw new Exception('Payment not found');
            }

            if (!$payment->canBeRefunded()) {
                throw new Exception('Payment cannot be refunded in current status');
            }

            if ($amount > $payment->amount) {
                throw new Exception('Refund amount cannot exceed payment amount');
            }

            $metadata = $payment->metadata ?? [];
            $metadata['refund_amount'] = $amount;
            $metadata['refund_reason'] = $reason;
            $metadata['refunded_at'] = date('Y-m-d H:i:s');

            $this->paymentRepository->update($paymentId, [
                'status' => 'refunded',
                'metadata' => $metadata
            ]);

            Logger::info("Payment refunded", [
                'payment_id' => $paymentId,
                'order_id' => $payment->order_id,
                'amount' => $amount,
                'reason' => $reason
            ]);

            return $this->paymentRepository->find($paymentId);
        });
    }

    public function getPaymentsByOrder(int $orderId): \App\Framework\Support\Collection
    {
        return $this->paymentRepository->findByOrderId($orderId);
    }

    public function getPaymentByTransactionId(string $transactionId): ?Payment
    {
        return $this->paymentRepository->findByTransactionId($transactionId);
    }

    public function getPaymentByPaymentIntentId(string $paymentIntentId): ?Payment
    {
        return $this->paymentRepository->findByPaymentIntentId($paymentIntentId);
    }

    public function retryPayment(int $paymentId): Payment
    {
        return $this->database->transaction(function () use ($paymentId) {
            $payment = $this->paymentRepository->find($paymentId);
            if (!$payment) {
                throw new Exception('Payment not found');
            }

            if (!$payment->canBeRetried()) {
                throw new Exception('Payment cannot be retried in current status');
            }

            $this->paymentRepository->update($paymentId, [
                'status' => 'pending',
                'error_message' => null,
                'failed_at' => null
            ]);

            Logger::info("Payment retry initiated", [
                'payment_id' => $paymentId,
                'order_id' => $payment->order_id
            ]);

            return $this->paymentRepository->find($paymentId);
        });
    }

    public function getTotalCollected(?string $startDate = null, ?string $endDate = null): float
    {
        return $this->paymentRepository->getTotalCollected($startDate, $endDate);
    }

    public function validatePaymentAmount(int $orderId, float $amount): bool
    {
        $order = $this->orderRepository->find($orderId);
        if (!$order) {
            throw new Exception('Order not found');
        }

        // Check if payment amount matches order total
        return abs($amount - $order->total) < 0.01; // Allow for floating point precision
    }

    public function createSubscriptionPayment(int $subscriptionId, array $data): Payment
    {
        return $this->database->transaction(function () use ($subscriptionId, $data) {
            $subscription = $this->subscriptionRepository->find($subscriptionId);

            if (!$subscription) {
                throw new Exception('Subscription not found');
            }

            $paymentData = [
                'subscription_id' => $subscriptionId,
                'order_id' => null,
                'site_id' => $subscription->site_id,
                'payment_method' => $data['payment_method'],
                'payment_provider' => $data['payment_provider'] ?? 'stripe',
                'amount' => $data['amount'] ?? $subscription->price,
                'currency' => $data['currency'] ?? $subscription->currency,
                'status' => 'pending',
                'transaction_id' => $data['transaction_id'] ?? null,
                'payment_intent_id' => $data['payment_intent_id'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ];

            $payment = $this->paymentRepository->create($paymentData);

            Logger::info("Subscription payment created", [
                'payment_id' => $payment->id,
                'subscription_id' => $subscriptionId,
                'amount' => $payment->amount
            ]);

            return $payment;
        });
    }
}
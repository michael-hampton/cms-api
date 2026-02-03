<?php

namespace App\Services\Billing\Order;

use App\Framework\Database\Database;
use App\Models\Payment;
use App\Repositories\Billing\OrderRepository;
use App\Services\Billing\PaymentService;

class OrderPaymentService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly PaymentService  $paymentService,
        private readonly Database        $database
    )
    {
    }

    public function processPayment(int $orderId, array $paymentData, int $siteId): Payment
    {
        return $this->database->transaction(function () use ($orderId, $paymentData, $siteId) {
            $order = $this->orderRepository->find($orderId);
            if (!$order) {
                throw new \Exception('Order not found');
            }

            $payment = $this->paymentService->createPayment($orderId, $paymentData, $siteId);

            $paymentMethod = $this->paymentService->paymentMethodRepository
                ->findByCode($paymentData['payment_method']);

            if ($paymentMethod && !$paymentMethod->requiresProcessing()) {
                $payment = $this->paymentService->completePayment($payment->id);
            }

            return $payment;
        });
    }

    public function createOrderWithPayment(
        array                $orderData,
        array                $items,
        int                  $siteId,
        array                $paymentData,
        OrderCreationService $orderCreator
    ): array
    {
        return $this->database->transaction(function () use (
            $orderData,
            $items,
            $siteId,
            $paymentData,
            $orderCreator
        ) {
            $order = $orderCreator->create($orderData, $items, $siteId);

            $payment = null;
            if (!empty($paymentData['payment_method'])) {
                $payment = $this->paymentService->createPayment(
                    $order->id,
                    $paymentData,
                    $siteId
                );
            }

            return [
                'order' => $order,
                'payment' => $payment
            ];
        });
    }
}
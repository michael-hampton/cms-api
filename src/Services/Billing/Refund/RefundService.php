<?php

namespace App\Services\Billing\Refund;

use App\Enums\Refunds\RefundStatus;
use App\Enums\Refunds\RefundType;
use App\Events\Refunds\RefundCancelled;
use App\Events\Refunds\RefundCreated;
use App\Events\Refunds\RefundProcessed;
use App\Exceptions\Orders\OrderNotFoundException;
use App\Exceptions\Orders\OrderNotRefundableException;
use App\Exceptions\Orders\RefundAlreadyProcessedException;
use App\Exceptions\Orders\RefundNotCancellableException;
use App\Exceptions\Orders\RefundNotFoundException;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Models\Model;
use App\Models\Order;
use App\Models\Refund;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Billing\RefundRepository;

class RefundService
{
    private Database $database;

    public function __construct(
        private readonly RefundRepository         $refundRepository,
        private readonly OrderRepository          $orderRepository,
        private readonly RefundAmountCalculator   $amountCalculator,
        private readonly RefundAmountValidator    $amountValidator,
        private readonly RefundItemRestockHandler $restockHandler,
        private readonly OrderStatusUpdater       $orderStatusUpdater,
        private readonly EventDispatcher          $eventDispatcher,
        ?Database                                 $database
    )
    {
        $this->database = $database ?? Database::getInstance();
    }

    public function createRefund(array $data, ?int $userId = null): Refund
    {
        return $this->database->transaction(function () use ($data, $userId) {
            $order = $this->validateOrder($data['order_id']);

            $refundAmount = $this->determineRefundAmount($data);
            $this->amountValidator->validateAmount($order, $refundAmount);

            $refund = $this->createRefundRecord($order, $data, $refundAmount);

            if (!empty($data['items'])) {
                $this->createRefundItems($refund->id, $data['items']);
            }

            if ($data['restock_items'] ?? true) {
                $this->restockHandler->restockItems($refund->id);
            }

            $this->processRefund($refund->id, $userId);

            $this->orderStatusUpdater->updateAfterRefund($order);

            $refund = $this->refundRepository->find($refund->id);

            if ($data['notify_customer'] ?? true) {
                $this->eventDispatcher->dispatch(
                    new RefundCreated($refund, $order, $data['reason'])
                );
            }

            return $refund;
        });
    }

    public function processRefund(int $refundId, ?int $userId = null): bool
    {
        return $this->database->transaction(function () use ($refundId, $userId) {
            $refund = $this->findRefundOrFail($refundId);

            if (!$refund->isPending()) {
                throw RefundAlreadyProcessedException::forId($refundId);
            }

            $result = $this->refundRepository->updateRefundStatus(
                $refundId,
                RefundStatus::PROCESSED->value,
                $userId
            );

            $this->eventDispatcher->dispatch(new RefundProcessed($refund, $userId));

            return $result;
        });
    }

    public function getRefundsByOrder(int $orderId): \App\Framework\Support\Collection
    {
        return $this->refundRepository->findByOrderId($orderId);
    }

    public function cancelRefund(int $refundId, ?int $userId = null): bool
    {
        return $this->database->transaction(function () use ($refundId, $userId) {
            $refund = $this->findRefundOrFail($refundId);

            if (!$refund->isPending()) {
                throw RefundNotCancellableException::forStatus($refund->status);
            }

            $result = $this->refundRepository->updateRefundStatus(
                $refundId,
                RefundStatus::CANCELLED->value,
                $userId
            );

            $this->eventDispatcher->dispatch(new RefundCancelled($refund, $userId));

            return $result;
        });
    }

    public function getRemainingRefundableAmount(int $orderId): float
    {
        $order = $this->orderRepository->find($orderId);

        if (!$order) {
            return 0.0;
        }

        return $this->amountValidator->getRemainingAmount($order);
    }

    private function validateOrder(int $orderId): Model
    {
        $order = $this->orderRepository->find($orderId);

        if (!$order) {
            throw OrderNotFoundException::forId($orderId);
        }

        if (!$order->canBeRefunded()) {
            throw OrderNotRefundableException::forOrder($orderId);
        }

        return $order;
    }

    private function determineRefundAmount(array $data): float
    {
        if (isset($data['refund_amount']) && $data['refund_amount'] > 0) {
            return (float)$data['refund_amount'];
        }

        return $this->amountCalculator->calculateFromItems($data['items'] ?? []);
    }

    private function createRefundRecord(Order $order, array $data, float $refundAmount): Model
    {
        $refundData = [
            'order_id' => $order->id,
            'refund_type' => RefundType::from($data['refund_type'] ?? 'full')->value,
            'refund_amount' => $refundAmount,
            'reason' => $data['reason'],
            'internal_notes' => $data['internal_notes'] ?? null,
            'notify_customer' => $data['notify_customer'] ?? true,
            'restock_items' => $data['restock_items'] ?? true,
            'status' => RefundStatus::PENDING->value,
            'site_id' => $order->site_id,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        return $this->refundRepository->create($refundData);
    }

    private function createRefundItems(int $refundId, array $items): void
    {
        foreach ($items as $item) {
            $refundItemData = [
                'refund_id' => $refundId,
                'order_item_id' => $item['id'] ?? null,
                'product_id' => $item['product_id'] ?? null,
                'product_name' => $item['product_name'],
                'quantity' => $item['quantity'] ?? 0,
                'refund_quantity' => $item['refund_quantity'] ?? $item['quantity'] ?? 0,
                'unit_price' => $item['unit_price'] ?? 0,
                'refund_amount' => $item['refund_amount'] ?? 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->refundRepository->createRefundItem($refundItemData);
        }
    }

    private function findRefundOrFail(int $refundId): Model
    {
        $refund = $this->refundRepository->find($refundId);

        if (!$refund) {
            throw RefundNotFoundException::forId($refundId);
        }

        return $refund;
    }
}
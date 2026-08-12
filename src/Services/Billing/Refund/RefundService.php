<?php

namespace App\Services\Billing\Refund;

use App\Enums\Refunds\RefundStatus;
use App\Enums\Refunds\RefundType;
use App\Events\Refunds\RefundCancelled;
use App\Events\Refunds\RefundCreated;
use App\Events\Refunds\RefundFailed;
use App\Events\Refunds\RefundProcessed;
use App\Exceptions\Orders\OrderNotFoundException;
use App\Exceptions\Orders\OrderNotRefundableException;
use App\Exceptions\Orders\RefundAlreadyProcessedException;
use App\Exceptions\Orders\RefundNotCancellableException;
use App\Exceptions\Orders\RefundNotFoundException;
use App\Exceptions\Payments\RefundGatewayException;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Collection;
use App\Models\Order;
use App\Models\Refund;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Billing\RefundRepository;
use App\Services\Billing\Stripe\Contracts\StripeRefundGatewayInterface;
use DomainException;
use Throwable;

/**
 * Orchestrates the refund lifecycle for physical product orders.
 *
 * Important:
 * Stripe is not called inside the same DB transaction as refund creation.
 *
 * Why?
 * Stripe cannot roll back with the database.
 *
 * Safer flow:
 *   1. Validate order.
 *   2. Determine and validate refund amount.
 *   3. Create a local PENDING refund record.
 *   4. Create refund items.
 *   5. Commit the pending refund.
 *   6. Call Stripe.
 *   7. If Stripe succeeds:
 *        - Store Stripe refund data.
 *        - Mark refund PROCESSED.
 *        - Restock items if requested.
 *        - Update order status.
 *        - Dispatch processed event.
 *   8. If Stripe fails:
 *        - Mark refund FAILED.
 *        - Store failure reason.
 *        - Dispatch failed event.
 *
 * Services MAY NOT:
 *   - Access sessions or globals.
 *   - Build queries directly.
 *   - Format data for presentation.
 */
class RefundService
{
    private Database $database;

    public function __construct(
        private readonly RefundRepository             $refundRepository,
        private readonly OrderRepository              $orderRepository,
        private readonly RefundAmountCalculator       $amountCalculator,
        private readonly RefundAmountValidator        $amountValidator,
        private readonly RefundItemRestockHandler     $restockHandler,
        private readonly OrderStatusUpdater           $orderStatusUpdater,
        private readonly EventDispatcher              $eventDispatcher,
        private readonly StripeRefundGatewayInterface $stripeRefundGateway,
        ?Database                                     $database = null,
    ) {
        $this->database = $database ?? Database::getInstance();
    }

    /**
     * Create and process a refund.
     *
     * This supports both full and partial refunds.
     *
     * For Stripe-backed orders:
     *   - Creates a local pending refund.
     *   - Calls Stripe.
     *   - Marks the refund processed only after Stripe succeeds.
     *
     * For non-Stripe/manual orders:
     *   - Creates the local refund.
     *   - Marks it processed locally.
     *
     * @throws OrderNotFoundException
     * @throws OrderNotRefundableException
     * @throws \App\Exceptions\Orders\RefundAmountExceedsRemainingException
     * @throws RefundGatewayException
     */
    public function createRefund(array $data, ?int $userId = null): Refund
    {
        $order = $this->validateOrder((int) $data['order_id']);

        $this->assertOrderBelongsToCurrentSite($order);

        $refundAmount = $this->determineRefundAmount($data, $order);

        $this->amountValidator->validateAmount($order, $refundAmount);

        $refundType = $this->determineRefundType($order, $refundAmount);

        /**
         * First transaction:
         * Create the local pending refund and related refund item rows.
         *
         * No Stripe call here.
         */
        $refund = $this->database->transaction(function () use ($order, $data, $refundAmount, $refundType) {
            $refund = $this->createRefundRecord(
                order: $order,
                data: $data,
                refundAmount: $refundAmount,
                refundType: $refundType,
            );

            if (!empty($data['items'])) {
                $this->createRefundItems($refund->id, $data['items']);
            }

            $this->eventDispatcher->dispatch(
                new RefundCreated($refund, $order, $data['reason'])
            );

            return $refund;
        });

        $paymentIntentId = $this->resolvePaymentIntentId($order);

        /**
         * No PaymentIntent means this is treated as a local/manual refund.
         *
         * That may be valid for older/manual orders, but Stripe-backed orders
         * must go through Stripe.
         */
        if (empty($paymentIntentId)) {
            return $this->completeLocalRefund(
                refundId: $refund->id,
                orderId: $order->id,
                userId: $userId,
                shouldRestockItems: $data['restock_items'] ?? true,
            );
        }

        try {
            $this->issueStripeRefund(
                refund: $refund,
                order: $order,
                refundAmount: $refundAmount,
                paymentIntentId: $paymentIntentId,
            );
        } catch (RefundGatewayException $e) {
            $this->markRefundFailed(
                refundId: $refund->id,
                userId: $userId,
                reason: $e->getMessage(),
            );

            throw $e;
        } catch (Throwable $e) {
            $this->markRefundFailed(
                refundId: $refund->id,
                userId: $userId,
                reason: $e->getMessage(),
            );

            throw new RefundGatewayException(
                message: 'Refund failed: ' . $e->getMessage(),
                previous: $e,
            );
        }

        return $this->completeStripeRefund(
            refundId: $refund->id,
            orderId: $order->id,
            userId: $userId,
            shouldRestockItems: $data['restock_items'] ?? true,
        );
    }

    /**
     * Mark an existing pending refund as processed.
     *
     * This method intentionally does NOT call Stripe.
     *
     * Guard:
     * If the order is Stripe-backed and the refund has no stripe_refund_id,
     * this method refuses to process it.
     *
     * This prevents the old bug where a refund could be marked locally as
     * processed without money actually being refunded in Stripe.
     *
     * @throws RefundNotFoundException
     * @throws RefundAlreadyProcessedException
     * @throws RefundGatewayException
     */
    public function processRefund(int $refundId, ?int $userId = null): bool
    {
        return $this->database->transaction(function () use ($refundId, $userId) {
            $refund = $this->findRefundOrFail($refundId);

            if (!$refund->isPending()) {
                throw RefundAlreadyProcessedException::forId($refundId);
            }

            $order = $this->validateOrder((int) $refund->order_id);

            $this->assertOrderBelongsToCurrentSite($order);

            $paymentIntentId = $this->resolvePaymentIntentId($order);

            if (!empty($paymentIntentId) && empty($refund->stripe_refund_id)) {
                throw new RefundGatewayException(
                    'Cannot manually process a Stripe-backed refund without a Stripe refund ID.'
                );
            }

            $result = $this->refundRepository->updateRefundStatus(
                $refundId,
                RefundStatus::PROCESSED->value,
                $userId
            );

            $this->orderStatusUpdater->updateAfterRefund($order);

            $processedRefund = $this->findRefundOrFail($refundId);

            $this->eventDispatcher->dispatch(
                new RefundProcessed($processedRefund, $userId)
            );

            return $result;
        });
    }

    public function getRefundsByOrder(int $orderId): Collection
    {
        return $this->refundRepository->findByOrderId($orderId);
    }

    /**
     * @throws RefundNotFoundException
     * @throws RefundNotCancellableException
     */
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

            $cancelledRefund = $this->findRefundOrFail($refundId);

            $this->eventDispatcher->dispatch(
                new RefundCancelled($cancelledRefund, $userId)
            );

            return $result;
        });
    }

    public function getRemainingRefundableAmount(int $orderId): float
    {
        $order = $this->orderRepository->find($orderId);

        if (!$order instanceof Order) {
            return 0.0;
        }

        return $this->amountValidator->getRemainingAmount($order);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Completion flows
    // ─────────────────────────────────────────────────────────────────────

    private function completeStripeRefund(
        int $refundId,
        int $orderId,
        ?int $userId,
        bool $shouldRestockItems,
    ): Refund {
        return $this->database->transaction(function () use (
            $refundId,
            $orderId,
            $userId,
            $shouldRestockItems
        ) {
            $refund = $this->findRefundOrFail($refundId);

            if (!$refund->isPending()) {
                throw RefundAlreadyProcessedException::forId($refundId);
            }

            $order = $this->validateOrder($orderId);

            if ($shouldRestockItems) {
                $this->restockHandler->restockItems($refund->id);
            }

            $this->refundRepository->updateRefundStatus(
                $refund->id,
                RefundStatus::PROCESSED->value,
                $userId
            );

            $this->orderStatusUpdater->updateAfterRefund($order);

            $processedRefund = $this->findRefundOrFail($refund->id);

            $this->eventDispatcher->dispatch(
                new RefundProcessed($processedRefund, $userId)
            );

            return $processedRefund;
        });
    }

    private function completeLocalRefund(
        int $refundId,
        int $orderId,
        ?int $userId,
        bool $shouldRestockItems,
    ): Refund {
        return $this->database->transaction(function () use (
            $refundId,
            $orderId,
            $userId,
            $shouldRestockItems
        ) {
            $refund = $this->findRefundOrFail($refundId);

            if (!$refund->isPending()) {
                throw RefundAlreadyProcessedException::forId($refundId);
            }

            $order = $this->validateOrder($orderId);

            if ($shouldRestockItems) {
                $this->restockHandler->restockItems($refund->id);
            }

            $this->refundRepository->updateRefundStatus(
                $refund->id,
                RefundStatus::PROCESSED->value,
                $userId
            );

            $this->orderStatusUpdater->updateAfterRefund($order);

            $processedRefund = $this->findRefundOrFail($refund->id);

            $this->eventDispatcher->dispatch(
                new RefundProcessed($processedRefund, $userId)
            );

            return $processedRefund;
        });
    }

    private function markRefundFailed(int $refundId, ?int $userId, string $reason): void
    {
        $this->database->transaction(function () use ($refundId, $userId, $reason) {
            $refund = $this->findRefundOrFail($refundId);

            $this->refundRepository->update($refund->id, [
                'status'                => RefundStatus::FAILED->value,
                'stripe_failure_reason' => $reason,
                'processed_by'          => $userId,
                'updated_at'            => date('Y-m-d H:i:s'),
            ]);

            $failedRefund = $this->findRefundOrFail($refund->id);

            $this->eventDispatcher->dispatch(
                new RefundFailed($failedRefund, $userId, $reason)
            );
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Validation
    // ─────────────────────────────────────────────────────────────────────

    private function validateOrder(int $orderId): Order
    {
        $order = $this->orderRepository->find($orderId, ['payments']);

        if (!$order instanceof Order) {
            throw OrderNotFoundException::forId($orderId);
        }

        if (!$order->canBeRefunded()) {
            throw OrderNotRefundableException::forOrder($orderId);
        }

        return $order;
    }

    /**
     * Keep this method as a central guard.
     *
     * Wire it to SiteContext once available in this namespace/project.
     */
    private function assertOrderBelongsToCurrentSite(Order $order): void
    {
        /**
         * Example:
         *
         * $currentSiteId = SiteContext::getId();
         *
         * if ((int) $order->site_id !== (int) $currentSiteId) {
         *     throw new DomainException('Order does not belong to the current site.');
         * }
         *
         * Leaving this as a dedicated method means the call sites are already
         * protected once SiteContext is wired in.
         */
    }

    // ─────────────────────────────────────────────────────────────────────
    // Amount / type
    // ─────────────────────────────────────────────────────────────────────

    private function determineRefundAmount(array $data, Order $order): float
    {
        if (isset($data['refund_amount']) && (float) $data['refund_amount'] > 0) {
            return (float) $data['refund_amount'];
        }

        if (!empty($data['items'])) {
            return $this->amountCalculator->calculateFromItems($data['items']);
        }

        // No amount or line items provided → refund the remaining refundable balance.
        return $this->amountValidator->getRemainingAmount($order);
    }

    private function determineRefundType(Order $order, float $refundAmount): RefundType
    {
        $remainingAmount = $this->amountValidator->getRemainingAmount($order);

        /**
         * If the refund amount covers the remaining refundable amount,
         * this is effectively a full refund.
         *
         * Otherwise it is partial, regardless of what the request claimed.
         */
        if ($refundAmount >= $remainingAmount) {
            return RefundType::FULL;
        }

        return RefundType::PARTIAL;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Create records
    // ─────────────────────────────────────────────────────────────────────

    private function createRefundRecord(
        Order $order,
        array $data,
        float $refundAmount,
        RefundType $refundType,
    ): Refund {
        $refund = $this->refundRepository->create([
            'order_id'        => $order->id,
            'refund_type'     => $refundType->value,
            'refund_amount'   => $refundAmount,
            'reason'          => $data['reason'],
            'internal_notes'  => $data['internal_notes'] ?? null,
            'notify_customer' => $data['notify_customer'] ?? true,
            'restock_items'   => $data['restock_items'] ?? true,
            'status'          => RefundStatus::PENDING->value,
            'site_id'         => $order->site_id,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        if (!$refund instanceof Refund) {
            throw new DomainException('Refund repository did not return a Refund model.');
        }

        return $refund;
    }

    private function createRefundItems(int $refundId, array $items): void
    {
        foreach ($items as $item) {
            $this->refundRepository->createRefundItem([
                'refund_id'       => $refundId,
                'order_item_id'   => $item['id'] ?? null,
                'product_id'      => $item['product_id'] ?? null,
                'product_name'    => $item['product_name'] ?? '',
                'quantity'        => $item['quantity'] ?? 0,
                'refund_quantity' => $item['refund_quantity'] ?? $item['quantity'] ?? 0,
                'unit_price'      => $item['unit_price'] ?? 0,
                'refund_amount'   => $item['refund_amount'] ?? 0,
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Stripe
    // ─────────────────────────────────────────────────────────────────────

    private function issueStripeRefund(
        Refund $refund,
        Order $order,
        float $refundAmount,
        string $paymentIntentId,
    ): void {
        $amountCents = $this->toMinorUnits($refundAmount);

        $result = $this->stripeRefundGateway->refundPaymentIntent(
            paymentIntentId: $paymentIntentId,
            amountCents: $amountCents,
            currency: strtolower($order->currency ?? 'gbp'),
            metadata: [
                'order_id'  => (string) $order->id,
                'refund_id' => (string) $refund->id,
                'site_id'   => (string) $order->site_id,
            ],

            /**
             * Your gateway interface should support this.
             *
             * Stripe idempotency prevents duplicate refunds if the request is retried.
             */
            idempotencyKey: 'order_refund_' . $refund->id,
        );

        $this->database->transaction(function () use ($refund, $result) {
            $this->refundRepository->update($refund->id, [
                'stripe_refund_id'     => $result->refundId,
                'stripe_refund_status' => $result->status,
                'stripe_refunded_at'   => date('Y-m-d H:i:s'),
                'updated_at'           => date('Y-m-d H:i:s'),
            ]);
        });
    }

    private function resolvePaymentIntentId(Order $order): ?string
    {
        if (!empty($order->payment_intent_id)) {
            return $order->payment_intent_id;
        }

        /**
         * If your Collection implementation supports first(), this is fine.
         * Otherwise move this lookup into OrderRepository.
         */
        $payment = $order->payments?->first();

        if ($payment && !empty($payment->payment_intent_id)) {
            return $payment->payment_intent_id;
        }

        return null;
    }

    private function toMinorUnits(float $amount): int
    {
        return (int) round($amount * 100);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Finders
    // ─────────────────────────────────────────────────────────────────────

    private function findRefundOrFail(int $refundId): Refund
    {
        $refund = $this->refundRepository->find($refundId);

        if (!$refund instanceof Refund) {
            throw RefundNotFoundException::forId($refundId);
        }

        return $refund;
    }
}
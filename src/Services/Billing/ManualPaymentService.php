<?php

namespace App\Services\Billing;

use App\Enums\ManualPaymentType;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Payment;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Members\MemberRepository;
use Exception;

/**
 * Creates offline payment records that appear in the member payment history.
 *
 * Manual payments do NOT:
 *   - Renew subscriptions
 *   - Trigger Stripe
 *   - Modify payment methods
 *   - Auto-create orders or line items
 *
 * They DO:
 *   - Create an auditable payment record
 *   - Appear in the CRM payments tab
 *   - Appear in the activity feed (via event)
 */
class ManualPaymentService
{
    private Database $database;

    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly MemberRepository        $memberRepository,
        ?Database                                $database = null,
    ) {
        $this->database = $database ?? Database::getInstance();
    }

    /**
     * @param array{
     *   type: string,
     *   amount: float,
     *   currency: string,
     *   reference: string|null,
     *   notes: string|null,
     *   received_at: string,
     *   subscription_id: int|null,
     *   order_id: int|null,
     * } $data
     */
    public function create(int $memberId, int $siteId, int $createdByUserId, array $data): Payment
    {
        return $this->database->transaction(function () use ($memberId, $siteId, $createdByUserId, $data) {
            $member = $this->memberRepository->find($memberId);

            if (!$member) {
                throw new Exception('Member not found');
            }

            $type = ManualPaymentType::from($data['type']);

            if ($data['amount'] <= 0) {
                throw new Exception('Amount must be greater than zero');
            }

            $payment = $this->paymentRepository->create([
                'member_id'       => $memberId,
                'site_id'         => $siteId,
                'payment_method'            => $type->value,
                'amount'          => round((float) $data['amount'], 2),
                'currency'        => strtoupper($data['currency'] ?? 'GBP'),
                'reference'       => $data['reference'] ?? null,
                'notes'           => $data['notes'] ?? null,
                'received_at'     => $data['received_at'],
                'created_by'      => $createdByUserId,
                'subscription_id' => $data['subscription_id'] ?? null,
                'order_id'        => $data['order_id'] ?? null,
            ]);

            Logger::info('Manual payment created', [
                'manual_payment_id' => $payment->id,
                'member_id'         => $memberId,
                'amount'            => $payment->amount,
                'type'              => $type->value,
                'created_by'        => $createdByUserId,
            ]);

            return $payment;
        });
    }

    public function delete(int $manualPaymentId, int $memberId): void
    {
        $this->database->transaction(function () use ($manualPaymentId, $memberId) {
            $payment = $this->paymentRepository->find($manualPaymentId);

            if (!$payment) {
                throw new Exception('Manual payment not found');
            }

            if ($payment->member_id !== $memberId) {
                throw new Exception('Manual payment does not belong to this member');
            }

            $this->paymentRepository->delete($manualPaymentId);

            Logger::info('Manual payment deleted', [
                'manual_payment_id' => $manualPaymentId,
                'member_id'         => $memberId,
            ]);
        });
    }
}
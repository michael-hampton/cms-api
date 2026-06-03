<?php

namespace App\Services\Billing\Stripe;

use App\Services\Billing\Stripe\Contracts\StripeRefundGatewayInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Wraps Stripe refund creation.
 *
 * Single responsibility: call the Stripe refunds API and return a
 * normalised result. No business logic, no DB writes.
 */
class StripeRefundGateway implements StripeRefundGatewayInterface
{
    public function __construct(
        private readonly StripeClient                      $stripe
    )
    {
    }

    /**
     * Issue a refund against a charge or payment intent.
     *
     * @param string $transactionId  Stripe charge ID (ch_…) or payment intent ID (pi_…).
     * @param float  $amount         Amount in decimal units (e.g. 9.99 for £9.99).
     * @param array  $options        Supported keys: reason (string), metadata (array).
     *
     * @return array{success: bool, refund_id?: string, amount?: float, status?: string, message?: string, error_code?: string}
     */
    public function refund(string $transactionId, float $amount, array $options = []): array
    {
        try {
            $params = ['amount' => (int) round($amount * 100)];

            if (str_starts_with($transactionId, 'pi_')) {
                $params['payment_intent'] = $transactionId;
            } else {
                $params['charge'] = $transactionId;
            }

            if (!empty($options['reason'])) {
                $params['reason'] = $this->normaliseRefundReason((string)$options['reason']);
            }

            if (!empty($options['metadata'])) {
                $params['metadata'] = $options['metadata'];
            }

            $refund = $this->stripe->refunds->create($params);

            return [
                'success'   => true,
                'refund_id' => $refund->id,
                'amount'    => $refund->amount / 100,
                'status'    => $refund->status,
            ];

        } catch (ApiErrorException $e) {
            return [
                'success'    => false,
                'message'    => $e->getMessage(),
                'error_code' => $e->getStripeCode(),
            ];
        }
    }

    public function findRefundableTransactionForInvoice(string $invoiceId): ?string
    {
        try {
            $invoice = $this->stripe->invoices->retrieve($invoiceId, [
                'expand' => ['payment_intent'],
            ]);

            $paymentIntent = $invoice->payment_intent ?? null;

            if (is_string($paymentIntent)) {
                return $paymentIntent;
            }

            if (is_object($paymentIntent) && !empty($paymentIntent->id)) {
                return $paymentIntent->id;
            }

            $legacyCharge = $invoice->charge ?? null;

            if (is_string($legacyCharge)) {
                return $legacyCharge;
            }

            if (is_object($legacyCharge) && !empty($legacyCharge->id)) {
                return $legacyCharge->id;
            }

            $payments = $this->stripe->invoicePayments->all([
                'invoice' => $invoiceId,
                'limit' => 10,
                'expand' => [
                    'data.payment.payment_intent',
                    'data.payment.charge',
                ],
            ]);

            foreach ($payments->data ?? [] as $invoicePayment) {
                if (($invoicePayment->status ?? null) !== 'paid') {
                    continue;
                }

                $payment = $invoicePayment->payment ?? null;
                $paymentIntent = is_object($payment) ? ($payment->payment_intent ?? null) : null;
                $charge = is_object($payment) ? ($payment->charge ?? null) : null;

                if (is_string($paymentIntent)) {
                    return $paymentIntent;
                }

                if (is_object($paymentIntent) && !empty($paymentIntent->id)) {
                    return $paymentIntent->id;
                }

                if (is_string($charge)) {
                    return $charge;
                }

                if (is_object($charge) && !empty($charge->id)) {
                    return $charge->id;
                }
            }

            return null;
        } catch (ApiErrorException) {
            return null;
        }
    }

    private function normaliseRefundReason(string $reason): string
    {
        return match ($reason) {
            'duplicate', 'fraudulent', 'requested_by_customer' => $reason,
            'customer_request', 'early_cancellation', 'manual_override', 'partial_service_failure' => 'requested_by_customer',
            default => 'requested_by_customer',
        };
    }
}

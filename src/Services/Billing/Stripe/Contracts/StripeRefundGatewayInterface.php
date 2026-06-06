<?php

namespace App\Services\Billing\Stripe\Contracts;

use App\DTO\Payments\StripeRefundResult;

interface StripeRefundGatewayInterface
{
    /**
     * @param string $transactionId  Stripe charge ID (ch_…) or payment intent ID (pi_…).
     * @param float  $amount         Decimal amount (e.g. 9.99).
     * @param array  $options        Supported keys: reason (string), metadata (array).
     *
     * @return array{success: bool, refund_id?: string, amount?: float, status?: string, message?: string, error_code?: string}
     */
    public function refund(string $transactionId, float $amount, array $options = []): array;

    public function findRefundableTransactionForInvoice(string $invoiceId): ?string;

    /**
     * Issue a refund against a Stripe PaymentIntent.
     *
     * @param string  $paymentIntentId  Stripe pi_… ID.
     * @param int     $amountCents      Amount to refund in minor units (e.g. 999 for £9.99).
     * @param string  $currency         ISO 4217 currency code (e.g. 'gbp').
     * @param array   $metadata         Arbitrary key/value metadata forwarded to Stripe.
     *
     * @throws \App\Exceptions\Payments\RefundGatewayException on Stripe API failure.
     */
    public function refundPaymentIntent(
        string $paymentIntentId,
        int    $amountCents,
        string $currency,
        array  $metadata = [],
        ?string $idempotencyKey = null,
    ): StripeRefundResult;
}

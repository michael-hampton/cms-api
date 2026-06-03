<?php

namespace App\Services\Billing\Stripe\Contracts;

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
}

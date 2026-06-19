<?php

namespace App\Services\Subscriptions;

use App\Models\Payment;
use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;

final class SubscriptionPaymentRecoveryService
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly SubscriptionInvoiceGateway $invoiceGateway,
    ) {
    }

    public function getListingData(Subscription $subscription): ?array
    {
        if (!in_array((string)$subscription->status, ['past_due', 'unpaid', 'suspended', 'failed'], true)) {
            return null;
        }

        $payment = $this->paymentRepository->findLatestRecoverableSubscriptionPayment((int)$subscription->id);
        if (!$payment) {
            return null;
        }

        $invoiceId = $this->invoiceId($payment);
        if ($invoiceId === null) {
            return null;
        }

        return [
            'invoice_id' => $invoiceId,
            'amount' => $this->formatLocalAmount($payment),
            'currency' => strtoupper((string)($payment->currency ?? '')),
            'failed_date' => $this->formatDate($payment->failed_at ?? null),
            'access_copy' => 'Access may be limited until payment is confirmed.',
        ];
    }

    public function settlementUrl(Subscription $subscription, int $memberId): string
    {
        if ((int)$subscription->member_id !== $memberId) {
            throw new \RuntimeException('Subscription not found.');
        }

        $payment = $this->paymentRepository->findLatestRecoverableSubscriptionPayment((int)$subscription->id);
        if (!$payment) {
            throw new \RuntimeException('This payment is no longer recoverable.');
        }

        $invoiceId = $this->invoiceId($payment);
        if ($invoiceId === null) {
            throw new \RuntimeException('This payment is no longer recoverable.');
        }

        $invoice = $this->invoiceGateway->retrieve($invoiceId);

        if (($invoice->status ?? null) !== 'open'
            || empty($invoice->hosted_invoice_url)
            || (int)($invoice->amount_remaining ?? 0) <= 0) {
            throw new \RuntimeException('This payment is no longer recoverable.');
        }

        return (string)$invoice->hosted_invoice_url;
    }

    private function invoiceId(Payment $payment): ?string
    {
        $invoiceId = $payment->stripe_invoice_id ?: $payment->transaction_id;

        return is_string($invoiceId) && str_starts_with($invoiceId, 'in_')
            ? $invoiceId
            : null;
    }

    private function formatLocalAmount(Payment $payment): string
    {
        $amount = (float)($payment->amount ?? 0);
        $currency = strtolower((string)($payment->currency ?? ''));
        $symbol = match ($currency) {
            'gbp' => '£',
            'usd' => '$',
            'eur' => '€',
            default => $currency !== '' ? strtoupper($currency) . ' ' : '',
        };

        if ($amount > 1000 && floor($amount) === $amount) {
            $amount /= 100;
        }

        return $symbol . number_format($amount, 2);
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('j M Y');
        }

        if (is_string($value) && $value !== '') {
            $timestamp = strtotime($value);
            return $timestamp === false ? null : date('j M Y', $timestamp);
        }

        return null;
    }
}

<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use App\Models\Payment;
use App\Repositories\Billing\PaymentRepository;
use Stripe\StripeClient;

final class SubscriptionPaymentRecoveryService
{
    private PaymentRepository $paymentRepository;
    private SubscriptionInvoiceGateway $invoiceGateway;

    public function __construct(
        ?StripeClient $stripe = null,
        ?PaymentRepository $paymentRepository = null,
        ?SubscriptionInvoiceGateway $invoiceGateway = null,
    )
    {
        $this->paymentRepository = $paymentRepository ?? new PaymentRepository();
        $this->invoiceGateway = $invoiceGateway ?? new SubscriptionInvoiceGateway($stripe);
    }

    public function getRecoveryData(Subscription $subscription): ?array
    {
        return $this->getListingData($subscription);
    }

    public function getListingData(Subscription $subscription): ?array
    {
        if (!in_array($subscription->status, ['past_due', 'unpaid', 'suspended', 'failed'], true)) {
            return null;
        }

        $payment = $this->paymentRepository
            ->findLatestRecoverableSubscriptionPayment((int)$subscription->id);

        if (!$payment || !$this->invoiceId($payment)) {
            return null;
        }

        // PaymentRepository stores major currency units (e.g. 12.99), not cents.
        $amountMajor = (float)($payment->amount ?? 0);

        return [
            'invoice_id' => $this->invoiceId($payment),
            'amount_cents' => (int) round($amountMajor * 100),
            'amount' => $this->formatMoney($amountMajor, (string)($payment->currency ?? '')),
            'currency' => strtoupper((string)($payment->currency ?? '')),
            'failed_date' => $this->formatDate($payment->failed_at ?? null),
            'access_copy' => 'Access may be limited until payment is confirmed.',
        ];
    }

    public function settlementUrl(
        Subscription $subscription,
        int $memberId,
        ?int $siteId = null,
    ): string
    {
        if ((int)$subscription->member_id !== $memberId
            || ($siteId !== null && (int)$subscription->site_id !== $siteId)) {
            throw new \RuntimeException('Subscription not found.');
        }

        $payment = $this->paymentRepository
            ->findLatestRecoverableSubscriptionPayment((int)$subscription->id);
        $invoiceId = $payment ? $this->invoiceId($payment) : null;

        if (!$invoiceId) {
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

    private function formatMoney(float $amountMajor, string $currency): string
    {
        $symbol = match (strtolower($currency)) {
            'gbp' => '£',
            'usd' => '$',
            'eur' => '€',
            default => strtoupper($currency) . ' ',
        };

        return $symbol . number_format($amountMajor, 2);
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

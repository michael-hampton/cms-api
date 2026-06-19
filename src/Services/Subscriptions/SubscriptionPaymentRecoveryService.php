<?php

namespace App\Services\Subscriptions;

use App\Models\Payment;
use App\Models\Subscription;
use Stripe\StripeClient;

final class SubscriptionPaymentRecoveryService
{
    private ?StripeClient $stripe;

    public function __construct(?StripeClient $stripe = null)
    {
        $this->stripe = $stripe;
    }

    public function getRecoveryData(Subscription $subscription): ?array
    {
        if (!in_array($subscription->status, ['past_due', 'unpaid', 'suspended', 'failed'], true)) {
            return null;
        }

        $payment = Payment::where('subscription_id', $subscription->id)
            ->whereIn('status', ['failed', 'pending', 'processing'])
            ->orderBy('created_at', 'desc')
            ->first();

        $invoiceId = $payment?->stripe_invoice_id ?: $payment?->transaction_id;
        if (!$invoiceId || !str_starts_with((string)$invoiceId, 'in_')) {
            return null;
        }

        try {
            $invoice = $this->stripe()->invoices->retrieve($invoiceId);
        } catch (\Throwable) {
            return null;
        }

        if (!in_array($invoice->status ?? null, ['open', 'uncollectible'], true)
            || empty($invoice->hosted_invoice_url)
            || (int)($invoice->amount_remaining ?? 0) <= 0) {
            return null;
        }

        return [
            'invoice_id' => $invoice->id,
            'amount_cents' => (int)$invoice->amount_remaining,
            'amount' => $this->formatMoney((int)$invoice->amount_remaining, (string)$invoice->currency),
            'currency' => strtoupper((string)$invoice->currency),
            'hosted_invoice_url' => (string)$invoice->hosted_invoice_url,
            'failed_date' => $payment?->failed_at?->format('j M Y'),
            'access_copy' => 'Access may be limited until payment is confirmed.',
        ];
    }

    private function stripe(): StripeClient
    {
        return $this->stripe ??= new StripeClient(
            $_ENV['STRIPE_SECRET_KEY'] ?? config('payment.stripe.secret_key')
        );
    }

    public function settlementUrl(Subscription $subscription, int $memberId, int $siteId): string
    {
        if ((int)$subscription->member_id !== $memberId || (int)$subscription->site_id !== $siteId) {
            throw new \RuntimeException('Subscription not found.');
        }

        $recovery = $this->getRecoveryData($subscription);
        if (!$recovery) {
            throw new \RuntimeException('This payment is no longer recoverable.');
        }

        return $recovery['hosted_invoice_url'];
    }

    private function formatMoney(int $amountCents, string $currency): string
    {
        $symbol = match (strtolower($currency)) {
            'gbp' => '£',
            'usd' => '$',
            'eur' => '€',
            default => strtoupper($currency) . ' ',
        };

        return $symbol . number_format($amountCents / 100, 2);
    }
}

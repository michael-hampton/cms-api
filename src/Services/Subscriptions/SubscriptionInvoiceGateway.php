<?php

namespace App\Services\Subscriptions;

use Stripe\Invoice;
use Stripe\StripeClient;

class SubscriptionInvoiceGateway
{
    private ?StripeClient $stripe;

    public function __construct(?StripeClient $stripe = null)
    {
        $this->stripe = $stripe;
    }

    public function retrieve(string $invoiceId): Invoice
    {
        return $this->stripe()->invoices->retrieve($invoiceId);
    }

    private function stripe(): StripeClient
    {
        return $this->stripe ??= new StripeClient(
            $_ENV['STRIPE_SECRET_KEY'] ?? config('payment.stripe.secret_key')
        );
    }
}

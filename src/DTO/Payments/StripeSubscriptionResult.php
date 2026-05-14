<?php

namespace App\DTO\Payments;

class StripeSubscriptionResult
{
    public function __construct(
        public readonly bool    $success,
        public readonly ?string $subscriptionId = null,
        public readonly ?string $status = null,
        public readonly ?string $customerId = null,
        public readonly ?string $paymentIntentId = null,
        public readonly bool    $requiresAction = false,
        public readonly ?string $paymentIntentClientSecret = null,
        public readonly ?int    $currentPeriodStart = null,
        public readonly ?int    $currentPeriodEnd = null,
        public readonly ?string $message = null,
        public readonly ?string $errorCode = null,
        public readonly ?int    $invoiceAmountCents = null,  // added
        public readonly ?int    $invoiceTaxCents = null,     // added
    )
    {
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'subscription_id' => $this->subscriptionId,
            'status' => $this->status,
            'customer_id' => $this->customerId,
            'payment_intent_id' => $this->paymentIntentId,
            'requires_action' => $this->requiresAction,
            'payment_intent_client_secret' => $this->paymentIntentClientSecret,
            'current_period_start' => $this->currentPeriodStart,
            'current_period_end' => $this->currentPeriodEnd,
            'message' => $this->message,
            'error_code' => $this->errorCode,
            'invoice_amount_cents' => $this->invoiceAmountCents,
            'invoice_tax_cents' => $this->invoiceTaxCents,
        ];
    }
}

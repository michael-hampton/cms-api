<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\Payment;
use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;
use App\Services\Subscriptions\SubscriptionInvoiceGateway;
use App\Services\Subscriptions\SubscriptionPaymentRecoveryService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Invoice;

final class SubscriptionPaymentRecoveryServiceTest extends TestCase
{
    private PaymentRepository&MockObject $payments;
    private SubscriptionInvoiceGateway&MockObject $invoices;
    private SubscriptionPaymentRecoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payments = $this->createMock(PaymentRepository::class);
        $this->invoices = $this->createMock(SubscriptionInvoiceGateway::class);
        $this->service = new SubscriptionPaymentRecoveryService($this->payments, $this->invoices);
    }

    public function test_listing_data_uses_local_payment_without_calling_stripe(): void
    {
        $payment = $this->payment('in_123');
        $payment->amount = 12.99;
        $payment->currency = 'GBP';
        $this->payments->method('findLatestRecoverableSubscriptionPayment')->with(10)->willReturn($payment);
        $this->invoices->expects($this->never())->method('retrieve');

        $data = $this->service->getListingData($this->subscription('past_due'));

        $this->assertSame('in_123', $data['invoice_id']);
        $this->assertSame('£12.99', $data['amount']);
    }

    public function test_listing_data_is_null_for_non_recoverable_subscription_state(): void
    {
        $this->payments->expects($this->never())->method('findLatestRecoverableSubscriptionPayment');
        $this->assertNull($this->service->getListingData($this->subscription('active')));
    }

    public function test_settlement_url_verifies_open_invoice(): void
    {
        $invoice = Invoice::constructFrom([
            'id' => 'in_123',
            'object' => 'invoice',
            'status' => 'open',
            'hosted_invoice_url' => 'https://example.test/invoice',
            'amount_remaining' => 1299,
        ]);
        $this->payments->method('findLatestRecoverableSubscriptionPayment')->willReturn($this->payment('in_123'));
        $this->invoices->expects($this->once())->method('retrieve')->with('in_123')->willReturn($invoice);

        $this->assertSame(
            'https://example.test/invoice',
            $this->service->settlementUrl($this->subscription('past_due'), 20, 30)
        );
    }

    public function test_uncollectible_invoice_is_not_recoverable(): void
    {
        $invoice = Invoice::constructFrom([
            'id' => 'in_123',
            'object' => 'invoice',
            'status' => 'uncollectible',
            'hosted_invoice_url' => 'https://example.test/invoice',
            'amount_remaining' => 1299,
        ]);
        $this->payments->method('findLatestRecoverableSubscriptionPayment')->willReturn($this->payment('in_123'));
        $this->invoices->method('retrieve')->willReturn($invoice);

        $this->expectException(\RuntimeException::class);
        $this->service->settlementUrl($this->subscription('past_due'), 20, 30);
    }

    public function test_settlement_rejects_wrong_member_or_site(): void
    {
        $this->payments->expects($this->never())->method('findLatestRecoverableSubscriptionPayment');
        $this->expectException(\RuntimeException::class);
        $this->service->settlementUrl($this->subscription('past_due'), 999, 30);
    }

    private function subscription(string $status): Subscription
    {
        $subscription = new Subscription();
        $subscription->id = 10;
        $subscription->member_id = 20;
        $subscription->site_id = 30;
        $subscription->status = $status;
        return $subscription;
    }

    private function payment(string $invoiceId): Payment
    {
        $payment = new Payment();
        $payment->status = 'failed';
        $payment->stripe_invoice_id = $invoiceId;
        return $payment;
    }
}

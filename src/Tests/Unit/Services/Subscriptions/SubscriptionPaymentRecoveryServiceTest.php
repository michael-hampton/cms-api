<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\Payment;
use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;
use App\Services\Subscriptions\SubscriptionInvoiceGateway;
use App\Services\Subscriptions\SubscriptionPaymentRecoveryService;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stripe\Invoice;

final class SubscriptionPaymentRecoveryServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_constructor_keeps_optional_stripe_client_as_first_parameter(): void
    {
        $parameters = (new \ReflectionMethod(SubscriptionPaymentRecoveryService::class, '__construct'))
            ->getParameters();

        self::assertSame('stripe', $parameters[0]->getName());
        self::assertTrue($parameters[0]->isOptional());
    }

    public function test_listing_uses_local_payment_data_without_invoice_lookup(): void
    {
        $payments = Mockery::mock(PaymentRepository::class);
        $invoices = Mockery::mock(SubscriptionInvoiceGateway::class);
        $subscription = $this->subscription(10, 20, 'past_due');
        $payment = $this->payment([
            'amount' => 12.99,
            'currency' => 'GBP',
            'transaction_id' => 'in_local',
            'failed_at' => '2026-06-19 12:00:00',
        ]);

        $payments->shouldReceive('findLatestRecoverableSubscriptionPayment')
            ->once()
            ->with(10)
            ->andReturn($payment);
        $invoices->shouldNotReceive('retrieve');

        $data = (new SubscriptionPaymentRecoveryService(null, $payments, $invoices))
            ->getListingData($subscription);

        self::assertSame('£12.99', $data['amount']);
        self::assertSame(1299, $data['amount_cents']);
        self::assertSame('in_local', $data['invoice_id']);
    }

    public function test_settlement_requires_member_ownership(): void
    {
        $payments = Mockery::mock(PaymentRepository::class);
        $invoices = Mockery::mock(SubscriptionInvoiceGateway::class);
        $payments->shouldNotReceive('findLatestRecoverableSubscriptionPayment');

        $this->expectException(\RuntimeException::class);
        (new SubscriptionPaymentRecoveryService(null, $payments, $invoices))
            ->settlementUrl($this->subscription(10, 20, 'past_due'), 99);
    }

    public function test_settlement_returns_verified_open_invoice_url(): void
    {
        $payments = Mockery::mock(PaymentRepository::class);
        $invoices = Mockery::mock(SubscriptionInvoiceGateway::class);
        $payment = $this->payment(['transaction_id' => 'in_open']);
        $invoice = Invoice::constructFrom([
            'id' => 'in_open',
            'status' => 'open',
            'amount_remaining' => 500,
            'hosted_invoice_url' => 'https://invoice.stripe.test/open',
        ]);

        $payments->shouldReceive('findLatestRecoverableSubscriptionPayment')->with(10)->andReturn($payment);
        $invoices->shouldReceive('retrieve')->once()->with('in_open')->andReturn($invoice);

        $url = (new SubscriptionPaymentRecoveryService(null, $payments, $invoices))
            ->settlementUrl($this->subscription(10, 20, 'past_due'), 20);

        self::assertSame('https://invoice.stripe.test/open', $url);
    }

    #[DataProvider('invalidInvoiceProvider')]
    public function test_settlement_rejects_invalid_invoice_states(
        string $status,
        int $amountRemaining,
        ?string $hostedUrl,
    ): void {
        $payments = Mockery::mock(PaymentRepository::class);
        $invoices = Mockery::mock(SubscriptionInvoiceGateway::class);
        $payments->shouldReceive('findLatestRecoverableSubscriptionPayment')
            ->andReturn($this->payment(['transaction_id' => 'in_invalid']));
        $invoices->shouldReceive('retrieve')->andReturn(Invoice::constructFrom([
            'id' => 'in_invalid',
            'status' => $status,
            'amount_remaining' => $amountRemaining,
            'hosted_invoice_url' => $hostedUrl,
        ]));

        $this->expectException(\RuntimeException::class);
        (new SubscriptionPaymentRecoveryService(null, $payments, $invoices))
            ->settlementUrl($this->subscription(10, 20, 'past_due'), 20);
    }

    public static function invalidInvoiceProvider(): array
    {
        return [
            'paid' => ['paid', 0, 'https://invoice.stripe.test/paid'],
            'void' => ['void', 500, 'https://invoice.stripe.test/void'],
            'uncollectible' => ['uncollectible', 500, 'https://invoice.stripe.test/uncollectible'],
            'missing url' => ['open', 500, null],
            'nothing remaining' => ['open', 0, 'https://invoice.stripe.test/empty'],
        ];
    }

    private function subscription(int $id, int $memberId, string $status): Subscription
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->setAttribute('id', $id);
        $subscription->setAttribute('member_id', $memberId);
        $subscription->setAttribute('status', $status);
        return $subscription;
    }

    private function payment(array $attributes): Payment
    {
        $payment = Mockery::mock(Payment::class)->makePartial();
        foreach ($attributes as $key => $value) {
            $payment->setAttribute($key, $value);
        }
        return $payment;
    }
}
